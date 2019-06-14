<?php
namespace tinymeng\uploads\Connector;


/**
 * 所有第三方上传类必须支持的接口方法
 */
interface GatewayInterface
{

    /**
     * @const  VISIBILITY_PUBLIC  public visibility
     */
    const VISIBILITY_PUBLIC = 'public';

    /**
     * @const  VISIBILITY_PRIVATE  private visibility
     */
    const VISIBILITY_PRIVATE = 'private';

    /**
     * Write a new file.
     *
     * @param string $path
     * @param string $contents
     *
     * @return array|false false on failure file meta data on success
     */
    public function write($path, $contents);

    /**
     * Write a new file using a stream.
     *
     * @param string   $path
     * @param resource $resource
     *
     * @return array|false false on failure file meta data on success
     */
    public function writeStream($path, $resource);

    /**
     * Update a file.
     *
     * @param string $path
     * @param string $contents
     *
     * @return array|false false on failure file meta data on success
     */
    public function update($path, $contents);

    /**
     * Update a file using a stream.
     *
     * @param string   $path
     * @param resource $resource
     *
     * @return array|false false on failure file meta data on success
     */
    public function updateStream($path, $resource);

    /**
     * Rename a file.
     *
     * @param string $path
     * @param string $newpath
     *
     * @return bool
     */
    public function rename($path, $newpath);

    /**
     * Copy a file.
     *
     * @param string $path
     * @param string $newpath
     *
     * @return bool
     */
    public function copy($path, $newpath);

    /**
     * Delete a file.
     *
     * @param string $path
     *
     * @return bool
     */
    public function delete($path);

    /**
     * Delete a directory.
     *
     * @param string $dirname
     *
     * @return bool
     */
    public function deleteDir($dirname);

    /**
     * Create a directory.
     *
     * @param string $dirname directory name
     *
     * @return array|false
     */
    public function createDir($dirname);

    /**
     * Set the visibility for a file.
     *
     * @param string $path
     * @param string $visibility
     *
     * @return array|false file meta data
     */
    public function setVisibility($path, $visibility);
}
