<?php
if (!defined('__TYPECHO_ROOT_DIR__')) exit;

/**
 * 图片自动压缩，自定义上传目录。
 *
 * @package MyUpload
 * @author jlice
 * @version 1.2.0
 * @link https://github.com/jlice/typecho-MyUpload
 */
include_once "functions.php";

class MyUpload_Plugin extends Widget_Upload implements Typecho_Plugin_Interface, Widget_Interface_Do
{
    // 图片文件后缀
    const IMG_EXT = array("jpg", "jpeg", "png");

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
            _t('图片压缩大小阈值'), _t('当图片大小超过此值（单位：KB）时，对图片进行压缩。（仅压缩jpg和png）'));
        $form->addInput($compress_larger);

        $upload_subdir = new Typecho_Widget_Helper_Form_Element_Radio('upload_subdir',
            array('year' => '年目录',
                'month' => '年月目录',
                'origin' => 'Typecho默认'),
            'origin', _t('图片上传路径'), _t('前两项会使用优化的图片重命名策略'));
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

        if (isset($file['tmp_name'])) {

            //移动上传文件
            if (!@move_uploaded_file($file['tmp_name'], $path)) {
                return false;
            }
        } else if (isset($file['bytes'])) {

            //直接写入文件
            if (!file_put_contents($path, $file['bytes'])) {
                return false;
            }
        } else {
            return false;
        }

        self::compressImage($path);
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

        if (isset($file['tmp_name'])) {

            @unlink($path);

            //移动上传文件
            if (!@move_uploaded_file($file['tmp_name'], $path)) {
                return false;
            }
        } else if (isset($file['bytes'])) {

            @unlink($path);

            //直接写入文件
            if (!file_put_contents($path, $file['bytes'])) {
                return false;
            }
        } else {
            return false;
        }

        self::compressImage($path);
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
