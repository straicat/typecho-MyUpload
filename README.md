# MyUpload

Typecho图片压缩上传插件

![插件演示](https://user-images.githubusercontent.com/9983385/69824169-ee671d80-1245-11ea-9600-2eaf2092cf25.gif)

## 使用方法

下载压缩包，将`MyUpload`文件夹上传到你的博客的`usr/plugins/`目录下，在后台启用，然后在插件设置里根据自己的需求设置插件：

![插件配置界面](https://user-images.githubusercontent.com/9983385/69823539-181f4500-1244-11ea-9925-795bff8e153e.png)

然后，还需要安装`jpegoptim`和`pngquant`。

Ubuntu系统下安装方法：

```bash
$ sudo apt install jpegoptim pngquant
```

## 说明

写博客时，如果不压缩图片，既比较费主机存储空间，还会非常拖慢页面加载速度，特别是对于带宽小的主机。可是，如果要压缩好图片后再上传又比较麻烦，放到对象存储上还另外要钱。于是乎，就撸了这个插件，在上传时自动压缩图片。压缩图片采用的方法是调用`jpegoptim`压缩jpg图片，调用`pngquant`压缩png图片，我觉得压缩效果挺好的，可以基本不降低清晰度且大幅降低图片占用空间。

除此之外，由于Typecho默认的图片重命名方式理论上可能出现重名覆盖，而且个人觉得把图片按月分文件夹有点麻烦，喜欢按年分文件夹。因此，对图片的重命名方式做了修改，不会出现重名覆盖，并且图片文件名也还是很短的，图片可以按年分文件夹，也按月分文件夹（和Typecho默认一样）。当然，这个需求比较小众，默认是保持Typecho默认的方式。

-----

我的博客：[木然轩](https://jlice.top/)

