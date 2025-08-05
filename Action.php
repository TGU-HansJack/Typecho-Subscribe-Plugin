<?php
if (!defined('__TYPECHO_ROOT_DIR__')) exit;

class Subscribe_Action extends Typecho_Widget implements Widget_Interface_Do
{
    public function action()
    {
        $do = $this->request->get('do');
        
        if ($do == 'unsubscribe') {
            $this->unsubscribe();
        } elseif ($do == 'subscribe') {
            $this->subscribe();
        }
    }
    
private function subscribe()
{
    $email = $this->request->get('email');
    $name = $this->request->get('name', '');
    
    if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $this->response->throwJson(['success' => false, 'message' => '请输入有效的邮箱地址']);
    }

    $db = Typecho_Db::get();
    $prefix = $db->getPrefix();
    
    // 检查是否已存在
    $exists = $db->fetchRow($db->select()->from($prefix . 'subscribers')->where('email = ?', $email));
    
    if ($exists) {
        if ($exists['status'] == 1) {
            $this->response->throwJson(['success' => false, 'message' => '该邮箱已经订阅']);
        } else {
            // 重新激活订阅
            $updateData = [
                'status' => 1, 
                'subscribe_time' => date('Y-m-d H:i:s'), 
                'unsubscribe_time' => null
            ];
            
            // 如果提供了名字，也更新名字
            if (!empty($name)) {
                $updateData['name'] = $name;
            }
            
            $db->query($db->update($prefix . 'subscribers')
                ->rows($updateData)
                ->where('email = ?', $email));
            
            // 发送订阅确认邮件
            Subscribe_Plugin::sendSubscribeNotification($email, $name);
            
            // 更新统计
            Subscribe_Plugin::updateStats();
            
            $this->response->throwJson(['success' => true, 'message' => '重新订阅成功，确认邮件已发送']);
        }
    } else {
        // 新增订阅
        $token = md5($email . time() . rand());
        $data = [
            'email' => $email,
            'name' => $name,
            'status' => 1,
            'token' => $token,
            'subscribe_time' => date('Y-m-d H:i:s')
        ];
        
        $db->query($db->insert($prefix . 'subscribers')->rows($data));
        
        // 发送订阅确认邮件
        Subscribe_Plugin::sendSubscribeNotification($email, $name);
        
        // 更新统计
        Subscribe_Plugin::updateStats();
        
        $this->response->throwJson(['success' => true, 'message' => '订阅成功，确认邮件已发送']);
    }
}

    
    private function unsubscribe()
    {
        $token = $this->request->get('token');
        
        if (!$token) {
            $this->showUnsubscribePage('参数错误', '缺少必要的参数。', false);
            return;
        }

        $db = Typecho_Db::get();
        $prefix = $db->getPrefix();
        
        $subscriber = $db->fetchRow($db->select()->from($prefix . 'subscribers')->where('token = ?', $token));
        
        if (!$subscriber) {
            $this->showUnsubscribePage('退订失败', '订阅记录不存在。', false);
            return;
        }

        if ($subscriber['status'] == 0) {
            $this->showUnsubscribePage('已退订', '您已经是退订状态。', true, $subscriber);
            return;
        }

        // 更新为退订状态
        $db->query($db->update($prefix . 'subscribers')
            ->rows(['status' => 0, 'unsubscribe_time' => date('Y-m-d H:i:s')])
            ->where('token = ?', $token));

        // 发送退订提醒邮件
        Subscribe_Plugin::sendUnsubscribeNotification($subscriber['email'], $subscriber['name']);
        
        // 更新统计
        Subscribe_Plugin::updateStats();

        $this->showUnsubscribePage('退订成功', '您已成功退订邮件通知。', true, $subscriber);
    }
    
    private function showUnsubscribePage($title, $message, $showResubscribe = false, $subscriber = null)
    {
        $options = Helper::options();
        $resubscribeUrl = $options->siteUrl . '/?subscribe=1';
        
        echo '<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>' . htmlspecialchars($title) . ' - ' . htmlspecialchars($options->title) . '</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { 
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .container {
            background: white;
            border-radius: 20px;
            padding: 40px;
            max-width: 500px;
            width: 100%;
            text-align: center;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
        }
        .icon {
            font-size: 60px;
            margin-bottom: 20px;
        }
        .success { color: #10b981; }
        .error { color: #ef4444; }
        h1 {
            font-size: 28px;
            margin-bottom: 16px;
            color: #1f2937;
        }
        p {
            font-size: 16px;
            color: #6b7280;
            margin-bottom: 30px;
            line-height: 1.6;
        }
        .btn {
            display: inline-block;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 12px 30px;
            border-radius: 25px;
            text-decoration: none;
            font-weight: 500;
            transition: transform 0.2s;
            margin: 0 10px;
        }
        .btn:hover {
            transform: translateY(-2px);
        }
        .btn-outline {
            background: transparent;
            border: 2px solid #667eea;
            color: #667eea;
        }
        .feedback-section {
            margin-top: 30px;
            padding-top: 30px;
            border-top: 1px solid #e5e7eb;
        }
        .feedback-section h3 {
            font-size: 18px;
            margin-bottom: 15px;
            color: #374151;
        }
        .feedback-options {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            justify-content: center;
            margin-bottom: 20px;
        }
        .feedback-btn {
            background: #f3f4f6;
            border: 1px solid #d1d5db;
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 14px;
            cursor: pointer;
            transition: all 0.2s;
        }
        .feedback-btn:hover {
            background: #e5e7eb;
        }
        .feedback-btn.selected {
            background: #667eea;
            color: white;
            border-color: #667eea;
        }
        .resubscribe-form {
            background: #f8fafc;
            padding: 20px;
            border-radius: 12px;
            margin-top: 20px;
        }
        .form-group {
            margin-bottom: 15px;
            text-align: left;
        }
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
            color: #374151;
        }
        .form-group input {
            width: 100%;
            padding: 10px;
            border: 1px solid #d1d5db;
            border-radius: 8px;
            font-size: 14px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="icon ' . ($showResubscribe ? 'success' : 'error') . '">
            ' . ($showResubscribe ? '✅' : '❌') . '
        </div>
        
        <h1>' . htmlspecialchars($title) . '</h1>
        <p>' . htmlspecialchars($message) . '</p>';
        
        if ($showResubscribe && $subscriber) {
            echo '
            <div class="feedback-section">
                <h3>告诉我们退订的原因？</h3>
                <div class="feedback-options">
                    <button class="feedback-btn" onclick="selectReason(this)">邮件太频繁</button>
                    <button class="feedback-btn" onclick="selectReason(this)">内容不感兴趣</button>
                    <button class="feedback-btn" onclick="selectReason(this)">邮件格式问题</button>
                    <button class="feedback-btn" onclick="selectReason(this)">其他原因</button>
                </div>
            </div>
            
            <div class="resubscribe-form" style="display: none;" id="resubscribe-form">
                <h3 style="margin-bottom: 15px;">我们会改进，希望您再次订阅</h3>
                <form onsubmit="resubscribe(event)">
                    <div class="form-group">
                        <label>邮箱地址</label>
                        <input type="email" value="' . htmlspecialchars($subscriber['email']) . '" readonly>
                    </div>
                    <div class="form-group">
                        <label>姓名（可选）</label>
                        <input type="text" name="name" value="' . htmlspecialchars($subscriber['name']) . '">
                    </div>
                    <button type="submit" class="btn">重新订阅</button>
                </form>
            </div>';
        }
        
        echo '
        <div style="margin-top: 30px;">
            <a href="' . $options->siteUrl . '" class="btn btn-outline">返回首页</a>';
            
        if ($showResubscribe) {
            echo '<button onclick="showResubscribeForm()" class="btn" id="show-resubscribe">考虑重新订阅</button>';
        }
        
        echo '
        </div>
    </div>
    
    <script>
        function selectReason(btn) {
            document.querySelectorAll(".feedback-btn").forEach(b => b.classList.remove("selected"));
            btn.classList.add("selected");
            
            setTimeout(() => {
                document.getElementById("resubscribe-form").style.display = "block";
                document.getElementById("show-resubscribe").style.display = "none";
            }, 500);
        }
        
        function showResubscribeForm() {
            document.getElementById("resubscribe-form").style.display = "block";
            document.getElementById("show-resubscribe").style.display = "none";
        }
        
        function resubscribe(event) {
            event.preventDefault();
            const name = event.target.name.value;
            const email = "' . ($subscriber ? $subscriber['email'] : '') . '";
            
            fetch("' . $options->siteUrl . '/action/subscribe", {
                method: "POST",
                headers: {
                    "Content-Type": "application/x-www-form-urlencoded",
                },
                body: "do=subscribe&email=" + encodeURIComponent(email) + "&name=" + encodeURIComponent(name)
            })
            .then(response => response.json())
            .then(data => {
                alert(data.message);
                if (data.success) {
                    location.reload();
                }
            })
            .catch(error => {
                alert("操作失败，请稍后重试");
            });
        }
    </script>
</body>
</html>';
    }
}
