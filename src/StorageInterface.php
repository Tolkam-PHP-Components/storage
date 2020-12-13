<?php declare(strict_types=1);

namespace Tolkam\Storage;

interface StorageInterface
{
    /**
     * Resolves actual file path
     *
     * @param string $filename
     *
     * @return string|null
     */
    public function getRealPath(string $filename): ?string;
    
    /**
     * Checks if file exists
     *
     * @param string $filename
     *
     * @return bool
     */
    public function has(string $filename): bool;
    
    /**
     * Gets file mime type
     *
     * @param string $filename
     *
     * @return string
     */
    public function getMime(string $filename): string;
    
    /**
     * Creates or replaces a file from string
     *
     * @param string        $filename
     * @param string        $contents
     * @param callable|null $next
     *
     * @return bool
     */
    public function put(string $filename, string $contents, callable $next = null): bool;
    
    /**
     * Creates or replaces a file from resource
     *
     * @param string        $filename
     * @param resource      $resource
     * @param callable|null $next
     *
     * @return bool
     */
    public function putFromStream(string $filename, $resource, callable $next = null): bool;
    
    /**
     * Reads file into string
     *
     * @param string $filename
     *
     * @return false|string
     */
    public function read(string $filename);
    
    /**
     * Reads file into stream
     *
     * @param string $filename
     *
     * @return false|resource
     */
    public function readToStream(string $filename);
    
    /**
     * Copies file
     *
     * @param string $sourceFilename
     * @param string $targetFilename
     * @param bool   $force Delete old target before copy
     *
     * @return bool
     */
    public function copy(string $sourceFilename, string $targetFilename, bool $force = false): bool;
    
    /**
     * Deletes file
     *
     * @param string $filename
     * @param bool   $deleteDirs
     *
     * @return bool
     */
    public function delete(string $filename, bool $deleteDirs = false): bool;
    
    /**
     * Deletes all files and their directories
     *
     * @param string ...$filenames
     *
     * @return bool
     */
    public function deleteAll(string ...$filenames);
}
