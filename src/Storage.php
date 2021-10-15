<?php declare(strict_types=1);

namespace Tolkam\Storage;

use League\Flysystem\FilesystemInterface;
use Throwable;
use Tolkam\UriGenerator\UriGeneratorInterface;

class Storage implements StorageInterface
{
    /**
     * @var FilesystemInterface
     */
    protected FilesystemInterface $filesystem;
    
    /**
     * @var UriGeneratorInterface
     */
    protected UriGeneratorInterface $uriGenerator;
    
    /**
     * @param FilesystemInterface   $filesystem
     * @param UriGeneratorInterface $uriGenerator
     */
    public function __construct(
        FilesystemInterface $filesystem,
        UriGeneratorInterface $uriGenerator
    ) {
        $this->filesystem = $filesystem;
        $this->uriGenerator = $uriGenerator;
    }
    
    /**
     * @inheritDoc
     */
    public function getRealPath(string $filename): ?string
    {
        $stream = $this->readToStream($filename);
        $metadata = stream_get_meta_data($stream);
        
        return $metadata ? ($metadata['uri'] ?? null) : null;
    }
    
    /**
     * @inheritDoc
     */
    public function has(string $filename): bool
    {
        return $this->filesystem->has($this->getPath($filename));
    }
    
    /**
     * @inheritDoc
     */
    public function getMime(string $filename): string
    {
        $this->ensureExists($filename);
        
        return $this->filesystem->getMimetype($this->getPath($filename));
    }
    
    /**
     * @inheritDoc
     */
    public function put(string $filename, string $contents, callable $next = null): bool
    {
        $path = $this->getPath($filename);
        
        return $this->transactional(function () use ($path, $contents, $next) {
            $result = $this->filesystem->put($path, $contents);
            $next && $next();
            
            return $result;
        }, function () use ($path) {
            $this->filesystem->delete($path);
        });
    }
    
    /**
     * @inheritDoc
     */
    public function putFromStream(string $filename, $resource, callable $next = null): bool
    {
        $path = $this->getPath($filename);
        
        return $this->transactional(function () use ($path, $resource, $next) {
            $result = $this->filesystem->putStream($path, $resource);
            $next && $next();
            
            return $result;
        }, function () use ($path) {
            $this->filesystem->delete($path);
        });
    }
    
    /**
     * @inheritDoc
     */
    public function read(string $filename)
    {
        $this->ensureExists($filename);
        
        return $this->filesystem->read($this->getPath($filename));
    }
    
    /**
     * @inheritDoc
     */
    public function readToStream(string $filename)
    {
        $this->ensureExists($filename);
        
        return $this->filesystem->readStream($this->getPath($filename));
    }
    
    /**
     * @inheritDoc
     */
    public function copy(string $sourceFilename, string $targetFilename, bool $force = false): bool
    {
        $this->ensureExists($sourceFilename);
        
        if ($force && $this->has($targetFilename)) {
            $this->delete($targetFilename);
        }
        
        return $this->filesystem->copy(
            $this->getPath($sourceFilename),
            $this->getPath($targetFilename)
        );
    }
    
    /**
     * @inheritDoc
     */
    public function delete(
        string $filename,
        bool $deleteDirs = false
    ): bool {
        
        if (!$this->has($filename)) {
            return false;
        }
        
        $filePath = $this->getPath($filename);
        $deleted = $this->filesystem->delete($filePath);
        
        if ($deleted && $deleteDirs && ($dir = pathinfo($filePath, PATHINFO_DIRNAME))) {
            $this->deleteEmptyDirs($dir);
        }
        
        return $deleted;
    }
    
    /**
     * @inheritDoc
     */
    public function deleteAll(string ...$filenames): bool
    {
        $left = count($filenames);
        
        foreach ($filenames as $name => $filename) {
            $this->delete($filename, true);
            if (!$this->has($filename)) {
                $left--;
            }
        }
        
        return $left === 0;
    }
    
    /**
     * Deletes empty directories
     *
     * @param string $path
     *
     * @return void
     */
    private function deleteEmptyDirs(string $path)
    {
        $prefix = '';
        $schemeSep = '://';
        $schemeSepPos = mb_strpos($path, $schemeSep);
        
        if ($schemeSepPos !== false) {
            $len = mb_strlen($schemeSep);
            $prefix = substr($path, 0, $schemeSepPos + $len);
            $path = substr($path, $schemeSepPos + $len);
        }
        
        $dirs = explode(DIRECTORY_SEPARATOR, $path);
        do {
            // no dirs left
            if (!$curDir = implode(DIRECTORY_SEPARATOR, $dirs)) {
                break;
            }
            // add prefix if found
            $curDir = $prefix . $curDir;
            
            // break if not empty
            $contents = $this->filesystem->listContents($curDir);
            if (!empty($contents)) {
                break;
            }
            
            $this->filesystem->deleteDir($curDir);
        } while (array_pop($dirs));
    }
    
    /**
     * Asserts file exists
     *
     * @param string $filename
     *
     * @throws StorageException
     */
    private function ensureExists(string $filename)
    {
        if (!$this->has($filename)) {
            throw new StorageException(sprintf(
                'File "%s" does not exist',
                $this->getPath($filename)
            ));
        }
    }
    
    /**
     * @param string $filename
     *
     * @return string
     */
    private function getPath(string $filename): string
    {
        return $this->uriGenerator->generate($filename);
    }
    
    /**
     * @param callable $onSuccess
     * @param callable $onFailure
     *
     * @return mixed
     * @throws Throwable
     */
    private function transactional(callable $onSuccess, callable $onFailure)
    {
        try {
            return $onSuccess();
        } catch (Throwable $t) {
            $onFailure();
            throw $t;
        }
    }
}
