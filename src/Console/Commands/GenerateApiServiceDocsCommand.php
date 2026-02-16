<?php

namespace mindtwo\TwoTility\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use mindtwo\TwoTility\Http\BaseApiClient;
use mindtwo\TwoTility\Http\CachedApiService;
use ReflectionClass;
use ReflectionMethod;
use ReflectionUnionType;

class GenerateApiServiceDocsCommand extends Command
{
    protected $signature = 'two-tility:generate-api-docs
                            {path? : The path to scan for CachedApiService implementations}
                            {--dry-run : Display changes without writing to files}';

    protected $description = 'Generate @method annotations for CachedApiService implementations';

    public function handle(): int
    {
        $path = $this->argument('path') ?? app_path('..');
        $dryRun = $this->option('dry-run');

        if (! File::isDirectory($path)) {
            $this->error("Path {$path} does not exist or is not a directory.");

            return self::FAILURE;
        }

        $this->info("Scanning for CachedApiService implementations in: {$path}");
        $files = $this->findPhpFiles($path);
        $updated = 0;

        foreach ($files as $file) {
            if ($this->processFile($file, $dryRun)) {
                $updated++;
            }
        }

        if ($updated === 0) {
            $this->info('No CachedApiService implementations found or updated.');
        } else {
            $message = $dryRun
                ? "Would update {$updated} file(s). Run without --dry-run to apply changes."
                : "Successfully updated {$updated} file(s).";
            $this->info($message);
        }

        return self::SUCCESS;
    }

    private function findPhpFiles(string $path): array
    {
        return File::allFiles($path);
    }

    private function processFile(string $filePath, bool $dryRun): bool
    {
        $content = File::get($filePath);

        // Extract namespace and class name
        if (! preg_match('/namespace\s+([^;]+);/i', $content, $namespaceMatch)) {
            return false;
        }

        if (! preg_match('/class\s+(\w+)\s+extends\s+CachedApiService/i', $content, $classMatch)) {
            return false;
        }

        $namespace = $namespaceMatch[1];
        $className = $classMatch[1];
        $fullClassName = "{$namespace}\\{$className}";

        // Try to load the class
        if (! class_exists($fullClassName)) {
            return false;
        }

        try {
            $reflection = new ReflectionClass($fullClassName);

            // Ensure it extends CachedApiService
            if (! $reflection->isSubclassOf(CachedApiService::class)) {
                return false;
            }

            // Get the client class
            $instance = $reflection->newInstanceWithoutConstructor();
            $getClientMethod = $reflection->getMethod('getClientClass');
            $clientClass = $getClientMethod->invoke($instance);

            // Generate method annotations
            $methods = $this->getClientMethods($clientClass);

            if (empty($methods)) {
                return false;
            }

            // Update the file content
            $newContent = $this->updateDocBlock($content, $className, $methods);

            if ($newContent === $content) {
                return false;
            }

            if ($dryRun) {
                $this->line("Would update: {$filePath}");
                $this->line("  Methods: ".implode(', ', array_column($methods, 'name')));
            } else {
                File::put($filePath, $newContent);
                $this->line("Updated: {$filePath}");
            }

            return true;
        } catch (\Exception $e) {
            $this->warn("Error processing {$filePath}: {$e->getMessage()}");

            return false;
        }
    }

    private function getClientMethods(string $clientClass): array
    {
        if (! class_exists($clientClass)) {
            return [];
        }

        $reflection = new ReflectionClass($clientClass);
        $methods = [];

        foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            // Skip magic methods, constructors, and methods from BaseApiClient
            if ($method->isConstructor() ||
                $method->isDestructor() ||
                str_starts_with($method->name, '__') ||
                $method->getDeclaringClass()->getName() === BaseApiClient::class
            ) {
                continue;
            }

            $methods[] = [
                'name' => $method->name,
                'returnType' => $this->getReturnType($method),
                'parameters' => $this->getParameters($method),
            ];
        }

        return $methods;
    }

    private function getReturnType(ReflectionMethod $method): string
    {
        $returnType = $method->getReturnType();

        if (! $returnType) {
            return 'mixed';
        }

        if ($returnType instanceof ReflectionUnionType) {
            $types = array_map(function ($type) use ($method) {
                return $this->formatType($type->getName(), $method);
            }, $returnType->getTypes());

            return implode('|', $types);
        }

        $type = $returnType->getName();

        return $this->formatType($type, $method);
    }

    private function formatType(string $type, ReflectionMethod $method): string
    {
        // Handle built-in types
        if (in_array($type, ['self', 'static'])) {
            return '\\'.$method->getDeclaringClass()->getName();
        }

        // Add leading backslash for class types
        if (! in_array($type, ['string', 'int', 'float', 'bool', 'array', 'object', 'mixed', 'void', 'null'])) {
            return '\\'.$type;
        }

        return $type;
    }

    private function getParameters(ReflectionMethod $method): string
    {
        $params = [];
        foreach ($method->getParameters() as $param) {
            $paramType = $param->getType();
            $type = $paramType ? $paramType->getName() : 'mixed';

            $paramStr = "{$type} \${$param->name}";

            if ($param->isOptional() && $param->isDefaultValueAvailable()) {
                $default = $param->getDefaultValue();
                $defaultStr = var_export($default, true);
                $paramStr .= " = {$defaultStr}";
            } elseif ($param->isOptional()) {
                $paramStr .= ' = null';
            }

            $params[] = $paramStr;
        }

        return implode(', ', $params);
    }

    private function updateDocBlock(string $content, string $className, array $methods): string
    {
        // Create a map of method names to their full annotations
        $methodMap = [];
        foreach ($methods as $method) {
            $params = $method['parameters'] ? "({$method['parameters']})" : '()';
            $methodMap[$method['name']] = " * @method {$method['returnType']} {$method['name']}{$params}";
        }

        // Pattern to match existing class docblock
        $pattern = '/(\/\*\*.*?\*\/)\s*class\s+'.$className.'/s';

        if (preg_match($pattern, $content, $matches)) {
            $existingDocBlock = $matches[1];

            // Split docblock into lines
            $lines = explode("\n", $existingDocBlock);
            $newLines = [];
            $hasNonMethodContent = false;

            // Process existing lines
            foreach ($lines as $line) {
                // Check if this is a closing */ line
                if (preg_match('/^\s*\*\/\s*$/', $line)) {
                    continue; // We'll add it back at the end
                }

                // Check if this is an @method line
                if (preg_match('/@method\s+.*?\s+(\w+)\s*\(/', $line, $methodMatch)) {
                    $methodName = $methodMatch[1];

                    // If we have a new version of this method, use it; otherwise keep existing
                    if (isset($methodMap[$methodName])) {
                        $newLines[] = $methodMap[$methodName];
                        unset($methodMap[$methodName]); // Mark as added
                    } else {
                        $newLines[] = $line; // Keep manual annotation
                    }
                } else {
                    $newLines[] = $line;

                    // Track if we have content other than just /** or *
                    if (! preg_match('/^\s*\/?\*+\s*$/', $line)) {
                        $hasNonMethodContent = true;
                    }
                }
            }

            // Add separator if there's existing non-method content and we're adding new methods
            if ($hasNonMethodContent && ! empty($methodMap)) {
                $newLines[] = ' *';
            }

            // Add remaining new methods that weren't in the existing docblock
            foreach ($methodMap as $methodAnnotation) {
                $newLines[] = $methodAnnotation;
            }

            // Close the docblock
            $newLines[] = ' */';

            $newDocBlock = implode("\n", $newLines);

            return str_replace($existingDocBlock, $newDocBlock, $content);
        } else {
            // No existing docblock, create one
            $methodAnnotations = array_values($methodMap);
            $newDocBlock = "/**\n".implode("\n", $methodAnnotations)."\n */\n";
            $replacement = $newDocBlock.'class '.$className;

            return preg_replace('/class\s+'.$className.'/', $replacement, $content, 1);
        }
    }
}
