<?php
// 处理AJAX请求 - 必须在任何输出之前
if (isset($_GET['action'])) {
    // 清理输出缓冲区
    while (ob_get_level()) {
        ob_end_clean();
    }
    
    // 设置正确的响应头
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-cache, must-revalidate');
    
    // 引入必要的文件
    if (!defined('__TYPECHO_ROOT_DIR__')) {
        define('__TYPECHO_ROOT_DIR__', dirname(dirname(dirname(__FILE__))));
        require_once __TYPECHO_ROOT_DIR__ . '/config.inc.php';
    }
    
    $db = Typecho_Db::get();
    $prefix = $db->getPrefix();
    $options = Helper::options();
    
    $action = $_GET['action'];
    
    try {
        switch ($action) {
            case 'get_chart_data':
                // 获取最近30天的统计数据
                $stats = $db->fetchAll($db->select()->from($prefix . 'subscriber_stats')
                    ->where('date >= ?', date('Y-m-d', strtotime('-30 days')))
                    ->order('date', Typecho_Db::SORT_ASC));
                
                $dates = [];
                $activeData = [];
                $newData = [];
                
                // 填充最近30天的数据
                for ($i = 29; $i >= 0; $i--) {
                    $date = date('Y-m-d', strtotime("-{$i} days"));
                    $dates[] = date('m-d', strtotime($date));
                    
                    $found = false;
                    foreach ($stats as $stat) {
                        if ($stat['date'] == $date) {
                            $activeData[] = intval($stat['active_count']);
                            $newData[] = intval($stat['new_count']);
                            $found = true;
                            break;
                        }
                    }
                    
                    if (!$found) {
                        $activeData[] = 0;
                        $newData[] = 0;
                    }
                }
                
                echo json_encode([
                    'dates' => $dates,
                    'activeData' => $activeData,
                    'newData' => $newData
                ], JSON_UNESCAPED_UNICODE);
                exit;
                
            case 'add_subscriber':
                $email = $_POST['email'] ?? '';
                $name = $_POST['name'] ?? '';
                
                if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    echo json_encode(['success' => false, 'message' => '邮箱格式不正确'], JSON_UNESCAPED_UNICODE);
                    exit;
                }

                $exists = $db->fetchRow($db->select()->from($prefix . 'subscribers')->where('email = ?', $email));
                if ($exists) {
                    echo json_encode(['success' => false, 'message' => '邮箱已存在'], JSON_UNESCAPED_UNICODE);
                    exit;
                }

                $token = md5($email . time() . rand());
                $data = [
                    'email' => $email,
                    'name' => $name,
                    'status' => 1,
                    'token' => $token,
                    'subscribe_time' => date('Y-m-d H:i:s')
                ];
                
                $db->query($db->insert($prefix . 'subscribers')->rows($data));
                
                // 更新统计
                Subscribe_Plugin::updateStats();
                
                echo json_encode(['success' => true, 'message' => '添加成功'], JSON_UNESCAPED_UNICODE);
                exit;
                
            case 'delete_subscribers':
                $ids = $_POST['ids'] ?? [];
                if (empty($ids)) {
                    echo json_encode(['success' => false, 'message' => '请选择要删除的记录'], JSON_UNESCAPED_UNICODE);
                    exit;
                }

                foreach ($ids as $id) {
                    $db->query($db->delete($prefix . 'subscribers')->where('id = ?', intval($id)));
                }
                
                // 更新统计
                Subscribe_Plugin::updateStats();
                
                echo json_encode(['success' => true, 'message' => '删除成功'], JSON_UNESCAPED_UNICODE);
                exit;
                
            case 'toggle_subscriber':
                $id = intval($_POST['id'] ?? 0);
                $subscriber = $db->fetchRow($db->select()->from($prefix . 'subscribers')->where('id = ?', $id));
                
                if (!$subscriber) {
                    echo json_encode(['success' => false, 'message' => '记录不存在'], JSON_UNESCAPED_UNICODE);
                    exit;
                }

                $newStatus = $subscriber['status'] == 1 ? 0 : 1;
                $updateData = ['status' => $newStatus];
                
                if ($newStatus == 0) {
                    $updateData['unsubscribe_time'] = date('Y-m-d H:i:s');
                } else {
                    $updateData['subscribe_time'] = date('Y-m-d H:i:s');
                    $updateData['unsubscribe_time'] = null;
                }

                $db->query($db->update($prefix . 'subscribers')->rows($updateData)->where('id = ?', $id));
                
                // 更新统计
                Subscribe_Plugin::updateStats();
                
                echo json_encode(['success' => true, 'message' => '状态更新成功'], JSON_UNESCAPED_UNICODE);
                exit;
                
            case 'send_mail':
                $articleIds = $_POST['article_ids'] ?? [];
                $subscriberIds = $_POST['subscriber_ids'] ?? [];
                $customSubject = $_POST['custom_subject'] ?? '';
                
                if (empty($articleIds)) {
                    echo json_encode(['success' => false, 'message' => '请选择要发送的文章'], JSON_UNESCAPED_UNICODE);
                    exit;
                }
                
                if (empty($subscriberIds)) {
                    echo json_encode(['success' => false, 'message' => '请选择要发送的订阅者'], JSON_UNESCAPED_UNICODE);
                    exit;
                }
                
                $result = Subscribe_Plugin::sendMailToSubscribers($articleIds, $subscriberIds, $customSubject);
                echo json_encode($result, JSON_UNESCAPED_UNICODE);
                exit;
                
            default:
                echo json_encode(['success' => false, 'message' => '未知操作'], JSON_UNESCAPED_UNICODE);
                exit;
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => '操作失败: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
        exit;
    }
}

// 正常页面显示
include 'header.php';
include 'menu.php';

$db = Typecho_Db::get();
$prefix = $db->getPrefix();

// 获取订阅者列表
$page = intval($request->get('page', 1));
$pageSize = 20;
$offset = ($page - 1) * $pageSize;

$subscribers = $db->fetchAll($db->select()->from($prefix . 'subscribers')
    ->order('id', Typecho_Db::SORT_DESC)
    ->offset($offset)
    ->limit($pageSize));

$total = $db->fetchObject($db->select(['COUNT(*)' => 'total'])->from($prefix . 'subscribers'))->total;
$totalPages = ceil($total / $pageSize);

// 统计信息
$activeCount = $db->fetchObject($db->select(['COUNT(*)' => 'count'])->from($prefix . 'subscribers')->where('status = ?', 1))->count;
$inactiveCount = $total - $activeCount;

// 获取最新文章（增加更多选项）
$articles = $db->fetchAll($db->select()->from($prefix . 'contents')
    ->where('status = ? AND type = ?', 'publish', 'post')
    ->order('created', Typecho_Db::SORT_DESC)
    ->limit(50));

// 获取发送记录
$sendLogs = $db->fetchAll($db->select()->from($prefix . 'send_log')
    ->order('send_time', Typecho_Db::SORT_DESC)
    ->limit(10));

// 获取插件配置
$pluginOptions = $options->plugin('Subscribe');

// 检查SMTP配置
$smtpConfigured = $pluginOptions->smtpHost && $pluginOptions->smtpUser && $pluginOptions->smtpPass;

// 更新统计数据
Subscribe_Plugin::updateStats();
?>

<style>
/* 管理页面样式 - 简约版本 */

/* CSS变量 - 便于自定义 */
:root {
    --admin-primary: #3b82f6;
    --admin-success: #10b981;
    --admin-error: #ef4444;
    --admin-warning: #f59e0b;
    --admin-text: #374151;
    --admin-text-light: #6b7280;
    --admin-bg: #ffffff;
    --admin-bg-light: #f8fafc;
    --admin-border: #e5e7eb;
    --admin-radius: 6px;
    --admin-shadow: 0 1px 3px rgba(0,0,0,0.1);
    --admin-spacing: 16px;
}

/* 基础重置 */
.subscribe * {
    box-sizing: border-box;
}

.subscribe {
    background: var(--admin-bg-light);
    min-height: 100vh;
}

/* 页面标题 */
.page-title {
    background: var(--admin-bg);
    padding: var(--admin-spacing);
    margin-bottom: var(--admin-spacing);
    border-radius: var(--admin-radius);
    box-shadow: var(--admin-shadow);
    border-left: 3px solid var(--admin-primary);
}

.page-title h2 {
    margin: 0;
    color: var(--admin-text);
    font-size: 25px;
    font-weight: 600;
}

/* 网格布局 */
.grid {
    display: grid;
    gap: var(--admin-spacing);
}

.grid-stats {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: var(--admin-spacing);
}

.grid-main {
    display: grid;
    grid-template-columns: 1fr 2fr;
    gap: var(--admin-spacing);
}

/* 卡片 */
.card {
    background: var(--admin-bg);
    border-radius: var(--admin-radius);
    box-shadow: var(--admin-shadow);
    border: 1px solid var(--admin-border);
}

.card-header {
    padding: 12px var(--admin-spacing);
    border-bottom: 1px solid var(--admin-border);
    font-weight: 500;
    font-size: 14px;
    color: var(--admin-text);
}

.card-body {
    padding: var(--admin-spacing);
}

/* 统计卡片 */
.stat-card {
    text-align: center;
    padding: 20px var(--admin-spacing);
}

.stat-number {
    font-size: 26px;
    font-weight: 700;
    color: var(--admin-text);
    display: block;
    margin-bottom: 4px;
}

.stat-label {
    font-size: 14px;
    color: var(--admin-text-light);
}

/* 图表 */
.chart-container {
    height: 300px;
}

#subscriber-chart {
    width: 100%;
    height: 100%;
}

/* 标签页 */
.tabs {
    background: var(--admin-bg);
    border-radius: var(--admin-radius);
    box-shadow: var(--admin-shadow);
    overflow: hidden;
}

.tab-nav {
    display: flex;
    border-bottom: 1px solid var(--admin-border);
}

.tab-link {
    padding: 12px 20px;
    text-decoration: none;
    color: var(--admin-text-light);
    font-size: 16px;
    font-weight: 500;
    border-bottom: 2px solid transparent;
    transition: all 0.2s;
}

.tab-link:hover {
    color: var(--admin-primary);
    background: var(--admin-bg-light);
}

.tab-link.active {
    color: var(--admin-primary);
    border-bottom-color: var(--admin-primary);
    background: var(--admin-bg-light);
}

.tab-content {
    display: none;
    padding: var(--admin-spacing);
}

.tab-content.active {
    display: block;
}

/* 表单 */
.form-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: var(--admin-spacing);
}

.form-group {
    margin-bottom: 12px;
}

.form-label {
    display: block;
    font-size: 15px;
    font-weight: 500;
    color: var(--admin-text);
    margin-bottom: 4px;
}

.form-input {
    width: 100%;
    padding: 8px 12px;
    border: 1px solid var(--admin-border);
    border-radius: var(--admin-radius);
    font-size: 15px;
    color: var(--admin-text);
    transition: border-color 0.2s;
}

.form-input:focus {
    outline: none;
    border-color: var(--admin-primary);
}

/* 选择框 */
.select-box {
    border: 1px solid var(--admin-border);
    border-radius: var(--admin-radius);
    overflow: hidden;
}

.select-header {
    background: var(--admin-bg-light);
    padding: 10px 12px;
    border-bottom: 1px solid var(--admin-border);
    font-size: 15px;
    font-weight: 500;
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 12px;
}

.select-header-left {
    display: flex;
    align-items: center;
    gap: 12px;
}

.select-header-right {
    display: flex;
    gap: 8px;
}

.select-content {
    max-height: 300px;
    overflow-y: auto;
}

.select-item {
    display: flex;
    align-items: flex-start;
    padding: 10px 12px;
    border-bottom: 1px solid var(--admin-border);
    cursor: pointer;
    transition: background-color 0.2s;
    gap: 8px;
}

.select-item:hover {
    background: var(--admin-bg-light);
}

.select-item:last-child {
    border-bottom: none;
}

.select-item input {
    margin-top: 2px;
    flex-shrink: 0;
}

.select-item-content {
    flex: 1;
    min-width: 0;
}

.item-title {
    font-size: 15px;
    color: var(--admin-text);
    margin-bottom: 2px;
    word-break: break-all;
}

.item-meta {
    font-size: 13px;
    color: var(--admin-text-light);
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
}

.item-meta-main {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
}

/* 按钮 */
.btn {
    display: inline-block;
    padding: 6px 12px;
    border: 1px solid var(--admin-border);
    border-radius: var(--admin-radius);
    background: var(--admin-bg);
    color: var(--admin-text);
    text-decoration: none;
    font-size: 15px;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.2s;
}

.btn:hover {
    background: var(--admin-bg-light);
}

.btn-primary {
    background: var(--admin-primary);
    border-color: var(--admin-primary);
    color: white;
}

.btn-primary:hover {
    background: #2563eb;
    border-color: #2563eb;
}

.btn-success {
    background: var(--admin-success);
    border-color: var(--admin-success);
    color: white;
}

.btn-danger {
    background: var(--admin-error);
    border-color: var(--admin-error);
    color: white;
}

.btn-sm {
    padding: 4px 8px;
    font-size: 13px;
}

/* 表格 */
.table {
    width: 100%;
    border-collapse: collapse;
    background: var(--admin-bg);
    border: 1px solid var(--admin-border);
    border-radius: var(--admin-radius);
    overflow: hidden;
}

.table th {
    background: var(--admin-bg-light);
    padding: 10px 12px;
    text-align: left;
    font-weight: 500;
    color: var(--admin-text);
    font-size: 15px;
    border-bottom: 1px solid var(--admin-border);
}

.table td {
    padding: 10px 12px;
    border-bottom: 1px solid var(--admin-border);
    font-size: 15px;
    color: var(--admin-text);
    vertical-align: top;
}

.table tr:last-child td {
    border-bottom: none;
}

.table tr:hover {
    background: var(--admin-bg-light);
}

/* 订阅者信息 */
.subscriber-info {
    display: flex;
    align-items: center;
    gap: 12px;
    flex-wrap: wrap;
}

.subscriber-email {
    font-weight: 500;
    color: var(--admin-text);
}

.subscriber-name {
    color: var(--admin-text-light);
    font-size: 14px;
}

.subscriber-status {
    font-size: 12px;
    font-weight: 500;
    padding: 2px 6px;
    border-radius: 3px;
}

.subscriber-status.active {
    background: #dcfce7;
    color: #166534;
}

.subscriber-status.inactive {
    background: #fee2e2;
    color: #991b1b;
}

/* 状态标签 */
.status {
    display: inline-block;
    padding: 2px 6px;
    border-radius: 3px;
    font-size: 12px;
    font-weight: 500;
}

.status-success {
    background: #dcfce7;
    color: #166534;
}

.status-error {
    background: #fee2e2;
    color: #991b1b;
}

/* 空状态 */
.empty {
    text-align: center;
    padding: 40px 20px;
    color: var(--admin-text-light);
}

.empty h6 {
    margin: 0 0 4px 0;
    font-size: 16px;
    color: var(--admin-text);
}

.empty p {
    margin: 0;
    font-size: 14px;
}

/* 模态框 */
.modal {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.5);
    z-index: 1000;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 20px;
}

.modal-content {
    background: var(--admin-bg);
    border-radius: var(--admin-radius);
    padding: 20px;
    max-width: 400px;
    width: 100%;
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
}

.modal-title {
    margin: 0 0 16px 0;
    color: var(--admin-text);
    font-size: 16px;
    font-weight: 600;
}

.modal-actions {
    text-align: right;
    margin-top: 16px;
}

.modal-actions .btn {
    margin-left: 8px;
}

/* 响应式 */
@media (max-width: 1200px) {
    .grid-main { grid-template-columns: 1fr; }
    .chart-container { height: 250px; }
}

@media (max-width: 768px) {
    .grid-stats { grid-template-columns: repeat(2, 1fr); }
    .form-grid { grid-template-columns: 1fr; }
    .tab-nav { flex-wrap: wrap; }
    .select-header { flex-direction: column; align-items: flex-start; }
    .select-header-right { margin-top: 8px; }
    .subscriber-info { flex-direction: column; align-items: flex-start; gap: 4px; }
}

@media (max-width: 480px) {
    .grid-stats { grid-template-columns: 1fr; }
    .tab-link { flex: 1; text-align: center; }
    .chart-container { height: 200px; }
}
</style>

<div class="main subscribe">
    <div class="body container">
        <!-- 页面标题 -->
        <div class="page-title">
            <h2>文章订阅管理</h2>
        </div>
        
        <div class="row typecho-page-main">
            <div class="col-mb-12">
                <!-- 配置状态 -->
                <div class="card" style="margin-bottom: var(--admin-spacing);">
                    <div class="card-header">系统状态</div>
                    <div class="card-body">
                        <div style="display: flex; gap: 20px; flex-wrap: wrap; font-size: 13px;">
                            <div>
                                <strong>SMTP配置:</strong> 
                                <span style="color: <?php echo $smtpConfigured ? 'var(--admin-success)' : 'var(--admin-error)'; ?>;">
                                    <?php echo $smtpConfigured ? '√ 已配置' : '× 未配置'; ?>
                                </span>
                            </div>
                            <div>
                                <strong>前端表单:</strong>
                                <a href="<?php echo $options->siteUrl; ?>?subscribe=1" target="_blank">测试</a>
                            </div>
                        </div>
                        
                        <?php if (!$smtpConfigured): ?>
                        <div style="background: #fef3c7; border: 1px solid #f59e0b; border-radius: 4px; padding: 12px; margin-top: 12px; font-size: 13px;">
                            <strong>注意:</strong> 请先配置SMTP服务器！
                            <a href="<?php echo $options->adminUrl('options-plugin.php?config=Subscribe'); ?>">前往配置</a>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- 统计和图表 -->
                <div class="grid-main" style="margin-bottom: var(--admin-spacing);">
                    <!-- 统计卡片 -->
                    <div class="grid-stats">
                        <div class="card stat-card">
                            <span class="stat-number" style="color: var(--admin-primary);"><?php echo $total; ?></span>
                            <span class="stat-label">总订阅者</span>
                        </div>
                        <div class="card stat-card">
                            <span class="stat-number" style="color: var(--admin-success);"><?php echo $activeCount; ?></span>
                            <span class="stat-label">活跃订阅</span>
                        </div>
                        <div class="card stat-card">
                            <span class="stat-number" style="color: var(--admin-error);"><?php echo $inactiveCount; ?></span>
                            <span class="stat-label">已退订</span>
                        </div>
                        <div class="card stat-card">
                            <span class="stat-number" style="color: var(--admin-warning);"><?php echo count($sendLogs); ?></span>
                            <span class="stat-label">发送记录</span>
                        </div>
                    </div>
                    
                    <!-- 图表 -->
                    <div class="card">
                        <div class="card-header">活跃用户趋势（最近30天）</div>
                        <div class="card-body">
                            <div class="chart-container">
                                <div id="subscriber-chart"></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 标签页 -->
                <div class="tabs">
                    <div class="tab-nav">
                        <a href="#" class="tab-link active" data-tab="send">发送邮件</a>
                        <a href="#" class="tab-link" data-tab="subscribers">订阅者管理</a>
                        <a href="#" class="tab-link" data-tab="logs">发送记录</a>
                    </div>

                    <!-- 发送邮件 -->
                    <div id="send" class="tab-content active">
                        <form id="send-form">
                            <div class="form-grid" style="margin-bottom: var(--admin-spacing);">
                                <!-- 选择文章 -->
                                <div class="select-box">
                                    <div class="select-header">
                                        <div class="select-header-left">
                                            <input type="checkbox" id="select-all-articles" />
                                            <span>选择文章</span>
                                        </div>
                                        <div class="select-header-right">
                                            <button type="button" id="select-latest-articles" class="btn btn-sm">最新5篇</button>
                                        </div>
                                    </div>
                                    <div class="select-content">
                                        <?php foreach ($articles as $article): ?>
                                        <div class="select-item">
                                            <input type="checkbox" name="article_ids[]" value="<?php echo $article['cid']; ?>">
                                            <div class="select-item-content">
                                                <div class="item-title"><?php echo htmlspecialchars($article['title']); ?></div>
                                                <div class="item-meta"><?php echo date('Y-m-d H:i', $article['created']); ?></div>
                                            </div>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                                
                                <!-- 选择订阅者 -->
                                <div class="select-box">
                                    <div class="select-header">
                                        <div class="select-header-left">
                                            <input type="checkbox" id="select-all-subscribers-send" />
                                            <span>选择订阅者</span>
                                        </div>
                                        <div class="select-header-right">
                                            <button type="button" id="select-active-subscribers" class="btn btn-sm">仅活跃</button>
                                        </div>
                                    </div>
                                    <div class="select-content">
                                        <?php foreach ($subscribers as $subscriber): ?>
                                        <div class="select-item">
                                            <input type="checkbox" name="subscriber_ids[]" value="<?php echo $subscriber['id']; ?>" <?php echo $subscriber['status'] == 1 ? 'checked' : ''; ?>>
                                            <div class="select-item-content">
                                                <div class="item-title"><?php echo htmlspecialchars($subscriber['email']); ?></div>
                                                <div class="item-meta">
                                                    <div class="item-meta-main">
                                                        <?php if ($subscriber['name']): ?>
                                                            <span><?php echo htmlspecialchars($subscriber['name']); ?></span>
                                                        <?php endif; ?>
                                                        <span class="subscriber-status <?php echo $subscriber['status'] == 1 ? 'active' : 'inactive'; ?>">
                                                            <?php echo $subscriber['status'] == 1 ? '活跃' : '已退订'; ?>
                                                        </span>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">自定义邮件标题（可选）</label>
                                <input type="text" name="custom_subject" class="form-input" placeholder="留空则使用默认标题">
                            </div>
                            
                            <div style="text-align: center; padding-top: var(--admin-spacing); border-top: 1px solid var(--admin-border);">
                                <button type="submit" class="btn btn-primary" <?php echo !$smtpConfigured ? 'disabled' : ''; ?>>
                                    发送邮件
                                </button>
                            </div>
                        </form>
                    </div>

                    <!-- 订阅者管理 -->
                    <div id="subscribers" class="tab-content">
                        <div style="margin-bottom: var(--admin-spacing);">
                            <button type="button" class="btn" id="add-subscriber">添加订阅者</button>
                            <button type="button" class="btn btn-danger" id="delete-selected">删除选中</button>
                        </div>

                        <form method="post" name="manage_subscribers">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th width="30"><input type="checkbox" class="select-all" /></th>
                                        <th>订阅者信息</th>
                                        <th width="120">订阅时间</th>
                                        <th width="80">操作</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!empty($subscribers)): ?>
                                        <?php foreach ($subscribers as $subscriber): ?>
                                        <tr>
                                            <td><input type="checkbox" value="<?php echo $subscriber['id']; ?>" name="ids[]"/></td>
                                            <td>
                                                <div class="subscriber-info">
                                                    <span class="subscriber-email"><?php echo htmlspecialchars($subscriber['email']); ?></span>
                                                    <?php if ($subscriber['name']): ?>
                                                        <span class="subscriber-name"><?php echo htmlspecialchars($subscriber['name']); ?></span>
                                                    <?php endif; ?>
                                                    <span class="subscriber-status <?php echo $subscriber['status'] == 1 ? 'active' : 'inactive'; ?>">
                                                        <?php echo $subscriber['status'] == 1 ? '已订阅' : '已退订'; ?>
                                                    </span>
                                                </div>
                                            </td>
                                            <td><?php echo date('m-d H:i', strtotime($subscriber['subscribe_time'])); ?></td>
                                            <td>
                                                <button type="button" class="btn btn-sm <?php echo $subscriber['status'] == 1 ? 'btn-danger' : 'btn-success'; ?> toggle-status" data-id="<?php echo $subscriber['id']; ?>">
                                                    <?php echo $subscriber['status'] == 1 ? '退订' : '激活'; ?>
                                                </button>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="4">
                                                <div class="empty">
                                                    <h6>暂无订阅者</h6>
                                                    <p>开始推广您的订阅表单吧！</p>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </form>
                    </div>

                    <!-- 发送记录 -->
                    <div id="logs" class="tab-content">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>邮件标题</th>
                                    <th width="100">文章数</th>
                                    <th width="100">收件人</th>
                                    <th width="120">发送时间</th>
                                    <th width="100">状态</th>
                                    <th width="100">操作</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($sendLogs)): ?>
                                    <?php foreach ($sendLogs as $log): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($log['subject']); ?></td>
                                        <td><?php echo count(explode(',', $log['article_ids'])); ?> 篇</td>
                                        <td><?php echo count(explode(',', $log['subscriber_ids'])); ?> 人</td>
                                        <td><?php echo date('m-d H:i', strtotime($log['send_time'])); ?></td>
                                        <td>
                                            <?php if ($log['status']): ?>
                                                <span class="status status-success">成功</span>
                                            <?php else: ?>
                                                <span class="status status-error">失败</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($log['error_msg']): ?>
                                                <button type="button" class="btn btn-sm view-error" data-error="<?php echo htmlspecialchars($log['error_msg']); ?>">错误</button>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="6">
                                            <div class="empty">
                                                <h6>暂无发送记录</h6>
                                                <p>发送第一封邮件后，记录将显示在这里</p>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- 添加订阅者模态框 -->
<div id="add-modal" class="modal" style="display: none;">
    <div class="modal-content">
        <h3 class="modal-title">添加订阅者</h3>
        <form id="add-form">
            <div class="form-group">
                <label class="form-label">邮箱地址</label>
                <input type="email" name="email" class="form-input" required>
            </div>
            <div class="form-group">
                <label class="form-label">姓名（可选）</label>
                <input type="text" name="name" class="form-input">
            </div>
            <div class="modal-actions">
                <button type="button" class="btn" onclick="closeModal()">取消</button>
                <button type="submit" class="btn btn-primary">添加</button>
            </div>
        </form>
    </div>
</div>

<?php
include 'copyright.php';
include 'common-js.php';
?>

<!-- 引入本地 ECharts -->
<script src="<?php echo $options->pluginUrl; ?>/Subscribe/assets/echarts.min.js"></script>

<script type="text/javascript">
(function() {
    function initSubscribe() {
        var $ = window.jQuery || window.$;
        if (!$) {
            setTimeout(initSubscribe, 100);
            return;
        }
        
        var currentUrl = '<?php echo $options->adminUrl('extending.php?panel=Subscribe/manage.php'); ?>';
        
        // 初始化图表
        initChart();
        
        // 标签页切换
        $('.tab-link').click(function(e) {
            e.preventDefault();
            var tab = $(this).data('tab');
            
            $('.tab-link').removeClass('active');
            $('.tab-content').removeClass('active');
            
            $(this).addClass('active');
            $('#' + tab).addClass('active');
        });
        
        // 全选文章
        $('#select-all-articles').change(function() {
            $('input[name="article_ids[]"]').prop('checked', $(this).prop('checked'));
        });
        
        // 选择最新文章
        $('#select-latest-articles').click(function() {
            $('input[name="article_ids[]"]').prop('checked', false);
            $('input[name="article_ids[]"]').slice(0, 5).prop('checked', true);
        });
        
        // 全选订阅者（发送页面）
        $('#select-all-subscribers-send').change(function() {
            $('input[name="subscriber_ids[]"]').prop('checked', $(this).prop('checked'));
        });
        
        // 发送邮件
        $('#send-form').submit(function(e) {
            e.preventDefault();
            
            var articleIds = [];
            var subscriberIds = [];
            
            $('input[name="article_ids[]"]:checked').each(function() {
                articleIds.push($(this).val());
            });
            
            $('input[name="subscriber_ids[]"]:checked').each(function() {
                subscriberIds.push($(this).val());
            });
            
            var customSubject = $('input[name="custom_subject"]').val();
            
            if (articleIds.length === 0) {
                alert('请选择要发送的文章');
                return;
            }
            
            if (subscriberIds.length === 0) {
                alert('请选择要发送的订阅者');
                return;
            }
            
            if (!confirm('确定要发送邮件给 ' + subscriberIds.length + ' 位订阅者吗？\n\n将发送 ' + articleIds.length + ' 篇文章')) {
                return;
            }
            
            var btn = $(this).find('button[type="submit"]');
            var originalText = btn.text();
            btn.prop('disabled', true).text('发送中...');
            
            $.ajax({
                url: currentUrl + '&action=send_mail',
                type: 'POST',
                data: {
                    article_ids: articleIds,
                    subscriber_ids: subscriberIds,
                    custom_subject: customSubject
                },
                dataType: 'json',
                timeout: 120000,
                success: function(data) {
                    alert(data.message);
                    btn.prop('disabled', false).text(originalText);
                    if (data.success) {
                        $('.tab-link[data-tab="logs"]').click();
                        setTimeout(function() {
                            location.reload();
                        }, 1000);
                    }
                },
                error: function(xhr, status, error) {
                    alert('发送失败: ' + error);
                    btn.prop('disabled', false).text(originalText);
                }
            });
        });
        
        // 选择订阅者
        $('#select-active-subscribers').click(function() {
            $('input[name="subscriber_ids[]"]').each(function() {
                var item = $(this).closest('.select-item');
                var isActive = item.find('.subscriber-status.active').length > 0;
                $(this).prop('checked', isActive);
            });
        });

        // 添加订阅者
        $('#add-subscriber').click(function() {
            $('#add-modal').show();
        });

        $('#add-form').submit(function(e) {
            e.preventDefault();
            var email = $(this).find('input[name="email"]').val();
            var name = $(this).find('input[name="name"]').val();
            
            $.ajax({
                url: currentUrl + '&action=add_subscriber',
                type: 'POST',
                data: { email: email, name: name },
                dataType: 'json',
                success: function(data) {
                    alert(data.message);
                    if (data.success) {
                        location.reload();
                    }
                },
                error: function(xhr, status, error) {
                    alert('请求失败: ' + error);
                }
            });
        });

        // 切换状态
        $('.toggle-status').click(function() {
            var id = $(this).data('id');
            
            $.ajax({
                url: currentUrl + '&action=toggle_subscriber',
                type: 'POST',
                data: { id: id },
                dataType: 'json',
                success: function(data) {
                    alert(data.message);
                    if (data.success) {
                        location.reload();
                    }
                },
                error: function(xhr, status, error) {
                    alert('请求失败: ' + error);
                }
            });
        });

        // 删除选中
        $('#delete-selected').click(function() {
            var ids = [];
            $('input[name="ids[]"]:checked').each(function() {
                ids.push($(this).val());
            });
            
            if (ids.length === 0) {
                alert('请选择要删除的记录');
                return;
            }
            
            if (!confirm('确定要删除选中的 ' + ids.length + ' 条记录吗？\n\n此操作不可恢复！')) {
                return;
            }
            
            $.ajax({
                url: currentUrl + '&action=delete_subscribers',
                type: 'POST',
                data: { ids: ids },
                dataType: 'json',
                success: function(data) {
                    alert(data.message);
                    if (data.success) {
                        location.reload();
                    }
                },
                error: function(xhr, status, error) {
                    alert('请求失败: ' + error);
                }
            });
        });

        // 查看错误
        $('.view-error').click(function() {
            var error = $(this).data('error');
            alert('错误信息：\n\n' + error);
        });

        // 全选功能
        $('.select-all').change(function() {
            $('input[name="ids[]"]').prop('checked', $(this).prop('checked'));
        });
        
        // 初始化图表
        function initChart() {
            if (typeof echarts === 'undefined') {
                console.log('ECharts not loaded');
                return;
            }
            
            var chartDom = document.getElementById('subscriber-chart');
            if (!chartDom) return;
            
            var myChart = echarts.init(chartDom);
            
            // 获取图表数据
            $.ajax({
                url: currentUrl + '&action=get_chart_data',
                type: 'GET',
                dataType: 'json',
                success: function(data) {
                    var option = {
                        tooltip: {
                            trigger: 'axis',
                            axisPointer: {
                                type: 'cross'
                            }
                        },
                        legend: {
                            data: ['活跃用户', '新增用户'],
                            textStyle: {
                                fontSize: 12
                            }
                        },
                        grid: {
                            left: '3%',
                            right: '4%',
                            bottom: '3%',
                            containLabel: true
                        },
                        xAxis: {
                            type: 'category',
                            boundaryGap: false,
                            data: data.dates,
                            axisLabel: {
                                fontSize: 10
                            }
                        },
                        yAxis: {
                            type: 'value',
                            axisLabel: {
                                fontSize: 10
                            }
                        },
                        series: [
                            {
                                name: '活跃用户',
                                type: 'line',
                                smooth: true,
                                lineStyle: {
                                    color: '#3b82f6',
                                    width: 2
                                },
                                itemStyle: {
                                    color: '#3b82f6'
                                },
                                areaStyle: {
                                    color: {
                                        type: 'linear',
                                        x: 0,
                                        y: 0,
                                        x2: 0,
                                        y2: 1,
                                        colorStops: [{
                                            offset: 0, color: 'rgba(59, 130, 246, 0.2)'
                                        }, {
                                            offset: 1, color: 'rgba(59, 130, 246, 0.05)'
                                        }]
                                    }
                                },
                                data: data.activeData
                            },
                            {
                                name: '新增用户',
                                type: 'line',
                                smooth: true,
                                lineStyle: {
                                    color: '#10b981',
                                    width: 2
                                },
                                itemStyle: {
                                    color: '#10b981'
                                },
                                areaStyle: {
                                    color: {
                                        type: 'linear',
                                        x: 0,
                                        y: 0,
                                        x2: 0,
                                        y2: 1,
                                        colorStops: [{
                                            offset: 0, color: 'rgba(16, 185, 129, 0.2)'
                                        }, {
                                            offset: 1, color: 'rgba(16, 185, 129, 0.05)'
                                        }]
                                    }
                                },
                                data: data.newData
                            }
                        ]
                    };
                    
                    myChart.setOption(option);
                },
                error: function() {
                    console.log('Failed to load chart data');
                }
            });
            
            // 响应式
            window.addEventListener('resize', function() {
                myChart.resize();
            });
        }
    }

    // 全局函数
    window.closeModal = function() {
        document.getElementById('add-modal').style.display = 'none';
        document.getElementById('add-form').reset();
    };

    // 初始化
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initSubscribe);
    } else {
        initSubscribe();
    }
})();
</script>

<?php include 'footer.php'; ?>
