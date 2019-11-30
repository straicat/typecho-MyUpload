<?php
if (!defined('__TYPECHO_ROOT_DIR__')) exit;

/**
 * 图片自动压缩，自定义上传目录。
 *
 * @package MyUpload
 * @author 文剑木然
 * @version 1.5.0
 * @link https://jlice.top
 */
include_once "functions.php";

class MyUpload_Plugin extends Widget_Upload implements Typecho_Plugin_Interface
{
    // 图片文件后缀
    const IMG_EXT = array("jpg", "jpeg", "png");
    // TinyPNG API URL
    const TINIFY_URL = 'https://api.tinify.com/shrink';

    /**
     * 启用插件方法,如果启用失败,直接抛出异常
     *
     * @static
     * @access public
     * @return void
     */
    public static function activate()
    {
        Typecho_Plugin::factory('Widget_Upload')->uploadHandle = array('MyUpload_Plugin', 'uploadHandle');
        Typecho_Plugin::factory('Widget_Upload')->modifyHandle = array('MyUpload_Plugin', 'modifyHandle');
    }

    /**
     * 禁用插件方法,如果禁用失败,直接抛出异常
     *
     * @static
     * @access public
     * @return void
     */
    public static function deactivate()
    {
        // TODO: Implement deactivate() method.
    }

    /**
     * 获取插件配置面板
     *
     * @static
     * @access public
     * @param Typecho_Widget_Helper_Form $form 配置面板
     * @return void
     */
    public static function config(Typecho_Widget_Helper_Form $form)
    {
        $compress_larger = new Typecho_Widget_Helper_Form_Element_Text('compress_larger', NULL, '50',
            _t('图片压缩大小阈值'), _t('当图片大小超过此值（单位：KB）时，对图片进行压缩。（仅压缩 jpg 和 png ）<br/>
如果使用云主机，须安装 <code>jpegoptim</code> 和 <code>pngquant</code>，不要填 TinyPNG API Key！<br/>
Ubuntu 系统下安装方法：<code>sudo apt install jpegoptim pngquant</code>'));
        $form->addInput($compress_larger);

        $apiKey = new Typecho_Widget_Helper_Form_Element_Text('apiKey', NULL, NULL,
            _t('TinyPNG API Key'),
            _t('如果使用虚拟主机，采用图片远程压缩。须先到 
<a href="https://tinypng.com/developers">https://tinypng.com/developers</a> 注册。<br/>
图片远程压缩速度很慢，请耐心等待。'));
        $form->addInput($apiKey);

        $upload_subdir = new Typecho_Widget_Helper_Form_Element_Radio('upload_subdir',
            array('year' => '年目录',
                'month' => '年月目录',
                'origin' => 'Typecho默认'),
            'origin', _t('图片上传路径'), _t('前两项会使用优化的图片重命名策略<hr>
GitHub: <a href="https://github.com/jlice/typecho-MyUpload">https://github.com/jlice/typecho-MyUpload</a>'));
        $form->addInput($upload_subdir);
    }

    /**
     * 个人用户的配置面板
     *
     * @access public
     * @param Typecho_Widget_Helper_Form $form
     * @return void
     */
    public static function personalConfig(Typecho_Widget_Helper_Form $form)
    {
        // TODO: Implement personalConfig() method.
    }

    private static function makeUploadDir($path)
    {
        $path = preg_replace("/\\\+/", '/', $path);
        $current = rtrim($path, '/');
        $last = $current;

        while (!is_dir($current) && false !== strpos($path, '/')) {
            $last = $current;
            $current = dirname($current);
        }

        if ($last == $current) {
            return true;
        }

        if (!@mkdir($last)) {
            return false;
        }

        $stat = @stat($last);
        $perms = $stat['mode'] & 0007777;
        @chmod($last, $perms);

        return self::makeUploadDir($path);
    }

    private static function getSafeName(&$name)
    {
        $name = str_replace(array('"', '<', '>'), '', $name);
        $name = str_replace('\\', '/', $name);
        $name = false === strpos($name, '/') ? ('a' . $name) : str_replace('/', '/a', $name);
        $info = pathinfo($name);
        $name = substr($info['basename'], 1);

        return isset($info['extension']) ? strtolower($info['extension']) : '';
    }

    /**
     * 优化的图片重命名策略
     *
     * @return string
     */
    public static function generateFilename()
    {
        return sprintf('%s', base_convert(uniqid(), 16, 36));
    }

    /**
     * 压缩图片
     *
     * @param $path
     * @throws Exception
     */
    public static function compressImage(&$path)
    {
        $compress_larger = htmlspecialchars(Typecho_Widget::widget('Widget_Options')->plugin('MyUpload')->compress_larger);
        $compress_larger = is_numeric($compress_larger) ? intval($compress_larger) * 1024 : 50 * 1024;

        $ext = strtolower(pathinfo($path)['extension']);

        // 如果是图片，而且图片较大，先对其进行压缩
        if (in_array($ext, self::IMG_EXT) && filesize($path) > $compress_larger) {
            if ($ext === "png") {
                compress_png_inplace($path);
            } elseif ($ext === "jpg" || $ext === "jpeg") {
                compress_jpg_inplace($path);
            }

            // 清除文件信息缓存，以获得正确的图片大小
            clearstatcache();
        }
    }

    /**
     * 远程压缩图片
     *
     * @param $bytes
     * @param $client
     * @return bool
     * @throws Typecho_Http_Client_Exception
     */
    public static function remoteCompressImage($bytes, $client) {
        if (!$client) return false;
        $client->setData($bytes);
        $client->setTimeout(60);
        $responseBody = $client->send(self::TINIFY_URL);
        if ($responseBody && ($responseBody = json_decode($responseBody))) {
            if ($picUrl = $responseBody->output->url) {
                $client->setMethod(Typecho_Http_Client::METHOD_GET);
                return $client->send($picUrl);
            }
        }
        return false;
    }

    /**
     * 上传文件处理函数,如果需要实现自己的文件哈希或者特殊的文件系统,请在options表里把uploadHandle改成自己的函数
     *
     * @access public
     * @param array $file 上传的文件
     * @return mixed
     * @throws Exception
     */
    public static function uploadHandle($file)
    {
        $ext = self::getSafeName($file['name']);

        if (!self::checkFileType($ext) || Typecho_Common::isAppEngine()) {
            return false;
        }

        $date = new Typecho_Date();

        $subdir_opt = htmlspecialchars(Typecho_Widget::widget('Widget_Options')->plugin('MyUpload')->upload_subdir);
        if ($subdir_opt === 'root') {
            $upload_subdir = '';
        } elseif ($subdir_opt === 'year') {
            $upload_subdir = '/' . $date->year;
        } else {
            $upload_subdir = '/' . $date->year . '/' . $date->month;
        }


        $path = Typecho_Common::url(defined('__TYPECHO_UPLOAD_DIR__') ? __TYPECHO_UPLOAD_DIR__ : self::UPLOAD_DIR,
                defined('__TYPECHO_UPLOAD_ROOT_DIR__') ? __TYPECHO_UPLOAD_ROOT_DIR__ : __TYPECHO_ROOT_DIR__)
            . $upload_subdir;

        //创建上传目录
        if (!is_dir($path)) {
            if (!self::makeUploadDir($path)) {
                return false;
            }
        }

        //获取文件名
        if ($subdir_opt == 'origin') {
            $fileName = sprintf('%u', crc32(uniqid())) . '.' . $ext;
        } else {
            $fileName = self::generateFilename() . '.' . $ext;
        }
        $path = $path . '/' . $fileName;

        $apiKey = Typecho_Widget::widget('Widget_Options')->plugin('MyUpload')->apiKey;
        if ($apiKey) {
            $client = Typecho_Http_Client::get();
            if ($client) {
                $client->setHeader('Authorization', 'Basic ' . base64_encode('api:' . $apiKey));
            }
        }

        if (isset($file['tmp_name'])) {
            // 使用远程图片压缩
            $remoteCompress = false;
            if (isset($client) && $client) {
                // 读取图片
                $fileHandler = fopen($file['tmp_name'], 'rb');
                $fileBytes = fread($fileHandler, filesize($file['tmp_name']));
                fclose($fileHandler);
                // 执行远程压缩
                try {
                    $picBytes = self::remoteCompressImage($fileBytes, $client);
                } catch (Typecho_Http_Client_Exception $exception) {}

                $remoteCompress = isset($picBytes) && $picBytes && file_put_contents($path, $picBytes);
            }

            // 如果远程压缩失败，放弃压缩
            if (!$remoteCompress) {
                //移动上传文件
                if (!@move_uploaded_file($file['tmp_name'], $path)) {
                    return false;
                }
            }
        } else if (isset($file['bytes'])) {
            // 似乎不会到这里

            // 使用远程图片压缩
            $remoteCompress = false;
            if (isset($client) && $client) {
                // 执行远程压缩
                try {
                    $picBytes = self::remoteCompressImage($file['bytes'], $client);
                } catch (Typecho_Http_Client_Exception $exception) {}

                $remoteCompress = isset($picBytes) && $picBytes && file_put_contents($path, $picBytes);
            }

            // 如果远程压缩失败，放弃压缩
            if (!$remoteCompress) {
                //直接写入文件
                if (!file_put_contents($path, $file['bytes'])) {
                    return false;
                }
            }
        } else {
            return false;
        }

        // 如果没有填写 apiKey，采取本地命令行压缩
        if (empty($apiKey)) {
            self::compressImage($path);
        }
        $ext = strtolower(pathinfo($path)['extension']);
        $file['size'] = filesize($path);

        //返回相对存储路径
        return array(
            'name' => $file['name'],
            'path' => (defined('__TYPECHO_UPLOAD_DIR__') ? __TYPECHO_UPLOAD_DIR__ : self::UPLOAD_DIR)
                . $upload_subdir . '/' . $fileName,
            'size' => $file['size'],
            'type' => $ext,
            'mime' => Typecho_Common::mimeContentType($path)
        );
    }

    /**
     * 修改文件处理函数,如果需要实现自己的文件哈希或者特殊的文件系统,请在options表里把modifyHandle改成自己的函数
     *
     * @access public
     * @param array $content 老文件
     * @param array $file 新上传的文件
     * @return mixed
     * @throws Exception
     */
    public static function modifyHandle($content, $file)
    {
        $ext = self::getSafeName($file['name']);

        if ($content['attachment']->type != $ext || Typecho_Common::isAppEngine()) {
            return false;
        }

        $path = Typecho_Common::url($content['attachment']->path,
            defined('__TYPECHO_UPLOAD_ROOT_DIR__') ? __TYPECHO_UPLOAD_ROOT_DIR__ : __TYPECHO_ROOT_DIR__);
        $dir = dirname($path);

        //创建上传目录
        if (!is_dir($dir)) {
            if (!self::makeUploadDir($dir)) {
                return false;
            }
        }

        $apiKey = Typecho_Widget::widget('Widget_Options')->plugin('MyUpload')->apiKey;
        if ($apiKey) {
            $client = Typecho_Http_Client::get();
            if ($client) {
                $client->setHeader('Authorization', 'Basic ' . base64_encode('api:' . $apiKey));
            }
        }

        if (isset($file['tmp_name'])) {

            @unlink($path);

            // 使用远程图片压缩
            $remoteCompress = false;
            if (isset($client) && $client) {
                // 读取图片
                $fileHandler = fopen($file['tmp_name'], 'rb');
                $fileBytes = fread($fileHandler, filesize($file['tmp_name']));
                fclose($fileHandler);
                // 执行远程压缩
                try {
                    $picBytes = self::remoteCompressImage($fileBytes, $client);
                } catch (Typecho_Http_Client_Exception $exception) {}

                $remoteCompress = isset($picBytes) && $picBytes && file_put_contents($path, $picBytes);
            }

            // 如果远程压缩失败，放弃压缩
            if (!$remoteCompress) {
                //移动上传文件
                if (!@move_uploaded_file($file['tmp_name'], $path)) {
                    return false;
                }
            }
        } else if (isset($file['bytes'])) {

            @unlink($path);

            // 使用远程图片压缩
            $remoteCompress = false;
            if (isset($client) && $client) {
                // 执行远程压缩
                try {
                    $picBytes = self::remoteCompressImage($file['bytes'], $client);
                } catch (Typecho_Http_Client_Exception $exception) {}

                $remoteCompress = isset($picBytes) && $picBytes && file_put_contents($path, $picBytes);
            }

            // 如果远程压缩失败，放弃压缩
            if (!$remoteCompress) {
                //直接写入文件
                if (!file_put_contents($path, $file['bytes'])) {
                    return false;
                }
            }
        } else {
            return false;
        }

        // 如果没有填写 apiKey，采取本地命令行压缩
        if (empty($apiKey)) {
            self::compressImage($path);
        }
        $file['size'] = filesize($path);

        //返回相对存储路径
        return array(
            'name' => $content['attachment']->name,
            'path' => $content['attachment']->path,
            'size' => $file['size'],
            'type' => $content['attachment']->type,
            'mime' => $content['attachment']->mime
        );
    }
}
