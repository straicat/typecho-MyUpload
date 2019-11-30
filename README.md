# MyUpload

Typecho图片压缩上传插件，支持本地压缩和 TinyPNG 远程压缩。

![插件演示](https://user-images.githubusercontent.com/9983385/69824169-ee671d80-1245-11ea-9600-2eaf2092cf25.gif)

## 使用方法

下载压缩包，将`MyUpload`文件夹上传到你的博客的`usr/plugins/`目录下，在后台启用，然后在插件设置里根据自己的需求设置插件：

![插件配置界面](https://user-images.githubusercontent.com/9983385/69898280-5460bb80-1392-11ea-821b-f5d5bc58a53a.png)

如果是云主机，须需要安装`jpegoptim`和`pngquant`。

Ubuntu系统下安装方法：

```bash
$ sudo apt install jpegoptim pngquant
```

如果是虚拟主机，可以采用远程压缩，须先到[https://tinypng.com/developers](https://tinypng.com/developers)注册一个 API Key：

![API Key](https://user-images.githubusercontent.com/9983385/69898314-cb964f80-1392-11ea-85ef-9846afddb040.png)

图片远程压缩速度很慢，请耐心等待（有可能超时导致压缩失败）。

## 说明

写博客时，如果不压缩图片，既比较费主机存储空间，还会非常拖慢页面加载速度，特别是对于带宽小的主机。可是，如果要压缩好图片后再上传又比较麻烦，放到对象存储上还另外要钱。于是乎，就撸了这个插件，在上传时自动压缩图片。压缩图片采用的方法是调用`jpegoptim`压缩jpg图片，调用`pngquant`压缩png图片，我觉得压缩效果挺好的，可以基本不降低清晰度且大幅降低图片占用空间。

UPDATE1：由于部分人反映希望能支持虚拟主机，那就加上远程压缩的功能吧，使用 TinyPNG 远程图片压缩服务，一个月免费压缩500张，应该是够用的。

除此之外，由于Typecho默认的图片重命名方式理论上可能出现重名覆盖，而且个人觉得把图片按月分文件夹有点麻烦，喜欢按年分文件夹。因此，对图片的重命名方式做了修改，不会出现重名覆盖，并且图片文件名也还是很短的，图片可以按年分文件夹，也按月分文件夹（和Typecho默认一样）。当然，这个需求比较小众，默认是保持Typecho默认的方式。

-----

我的博客：[木然轩](https://jlice.top/)

