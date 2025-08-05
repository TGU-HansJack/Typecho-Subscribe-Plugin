<?php
// 简单的调试文件
echo "<h2>RSSMailer 调试信息</h2>";

// 检查当前目录
echo "<h3>当前目录:</h3>";
echo __DIR__ . "<br>";

// 检查文件是否存在
echo "<h3>文件检查:</h3>";
$files = [
    'SimplePie autoloader' => __DIR__ . '/lib/SimplePie/autoloader.php',
    'PHPMailer' => __DIR__ . '/lib/PHPMailer/PHPMailer.php',
    'SMTP' => __DIR__ . '/lib/PHPMailer/SMTP.php',
    'Exception' => __DIR__ . '/lib/PHPMailer/Exception.php'
];

foreach ($files as $name => $path) {
    echo $name . ": " . ($exists = file_exists($path) ? '✓ 存在' : '✗ 不存在') . " - " . $path . "<br>";
}

// 检查缓存目录
echo "<h3>缓存目录:</h3>";
$cacheDir = __DIR__ . '/cache';
echo "路径: " . $cacheDir . "<br>";
echo "存在: " . (is_dir($cacheDir) ? '✓ 是' : '✗ 否') . "<br>";
echo "可写: " . (is_writable($cacheDir) ? '✓ 是' : '✗ 否') . "<br>";

// 尝试创建缓存目录
if (!is_dir($cacheDir)) {
    if (mkdir($cacheDir, 0755, true)) {
        echo "缓存目录创建成功<br>";
    } else {
        echo "缓存目录创建失败<br>";
    }
}

// 检查目录结构
echo "<h3>目录结构:</h3>";
function listDirectory($dir, $prefix = '') {
    if (is_dir($dir)) {
        $files = scandir($dir);
        foreach ($files as $file) {
            if ($file != '.' && $file != '..') {
                echo $prefix . $file;
                if (is_dir($dir . '/' . $file)) {
                    echo " (目录)<br>";
                    if (in_array($file, ['lib', 'cache'])) {
                        listDirectory($dir . '/' . $file, $prefix . '&nbsp;&nbsp;');
                    }
                } else {
                    echo "<br>";
                }
            }
        }
    }
}

listDirectory(__DIR__);
?>
