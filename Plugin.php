<?php
/**
 * 文章订阅插件 - 手动选择文章发送给订阅者
 * 
 * @package Subscribe
 * @author HansJack
 * @version 1.0
 * @link https://www.hansjack.com
 */

if (!defined('__TYPECHO_ROOT_DIR__')) exit;

class Subscribe_Plugin implements Typecho_Plugin_Interface
{
    /** @var string 控制菜单链接 */
    public static $panel = 'Subscribe/manage.php';

    /**
     * 激活插件
     */
    public static function activate()
    {
        // 创建数据库表
        self::createTable();
        
        // 注册Action处理器
        Helper::addAction('subscribe', 'Subscribe_Action');
        
        // 添加后台菜单
        Helper::addPanel(1, self::$panel, '文章订阅', '管理文章订阅', 'administrator');
        
        return _t('插件已激活，请进入设置页面配置SMTP服务器');
    }

    /**
     * 禁用插件
     */
    public static function deactivate()
    {
        Helper::removeAction('subscribe');
        Helper::removePanel(1, self::$panel);
    }

    /**
     * 插件配置
     */
    public static function config(Typecho_Widget_Helper_Form $form)
    {
        // SMTP配置分组
        $smtpGroup = new Typecho_Widget_Helper_Form_Element_Text('smtpGroup', NULL, '', _t('SMTP邮件配置'), _t('配置邮件发送服务器'));
        $smtpGroup->input->setAttribute('style', 'display:none');
        $form->addInput($smtpGroup);

        $smtpHost = new Typecho_Widget_Helper_Form_Element_Text('smtpHost', NULL, '', _t('SMTP服务器'), _t('如：smtp.gmail.com'));
        $form->addInput($smtpHost->addRule('required', _t('请填写SMTP服务器地址')));

        $smtpPort = new Typecho_Widget_Helper_Form_Element_Text('smtpPort', NULL, '587', _t('SMTP端口'), _t('通常为587或465'));
        $form->addInput($smtpPort->addRule('required', _t('请填写SMTP端口')));

        $smtpUser = new Typecho_Widget_Helper_Form_Element_Text('smtpUser', NULL, '', _t('SMTP用户名'), _t('发送邮件的邮箱地址'));
        $form->addInput($smtpUser->addRule('required', _t('请填写SMTP用户名')));

        $smtpPass = new Typecho_Widget_Helper_Form_Element_Password('smtpPass', NULL, '', _t('SMTP密码'), _t('邮箱密码或授权码'));
        $form->addInput($smtpPass->addRule('required', _t('请填写SMTP密码')));

        $smtpSecure = new Typecho_Widget_Helper_Form_Element_Select('smtpSecure', array(
            'tls' => 'TLS',
            'ssl' => 'SSL'
        ), 'tls', _t('加密方式'));
        $form->addInput($smtpSecure);

        $fromName = new Typecho_Widget_Helper_Form_Element_Text('fromName', NULL, '', _t('发件人名称'), _t('邮件显示的发件人名称'));
        $form->addInput($fromName);

        // 订阅提醒配置分组
        $notifyGroup = new Typecho_Widget_Helper_Form_Element_Text('notifyGroup', NULL, '', _t('订阅通知配置'), _t('配置订阅和退订通知邮件'));
        $notifyGroup->input->setAttribute('style', 'display:none');
        $form->addInput($notifyGroup);

        $subscribeSubject = new Typecho_Widget_Helper_Form_Element_Text('subscribeSubject', NULL, '感谢您的订阅！', _t('订阅成功邮件标题'));
        $form->addInput($subscribeSubject);

        $subscribeContent = new Typecho_Widget_Helper_Form_Element_Textarea('subscribeContent', NULL, '感谢您订阅我们的文章推送！我们会定期为您推送优质内容。', _t('订阅成功邮件内容'));
        $form->addInput($subscribeContent);

        $unsubscribeSubject = new Typecho_Widget_Helper_Form_Element_Text('unsubscribeSubject', NULL, '希望您能再次回来！', _t('退订提醒邮件标题'));
        $form->addInput($unsubscribeSubject);

        $unsubscribeContent = new Typecho_Widget_Helper_Form_Element_Textarea('unsubscribeContent', NULL, '很遗憾您选择了退订。如果是因为邮件频率或内容问题，欢迎随时联系我们。希望您能再次回来！', _t('退订提醒邮件内容'));
        $form->addInput($unsubscribeContent);

        // 文章推送邮件模板配置分组
        $templateGroup = new Typecho_Widget_Helper_Form_Element_Text('templateGroup', NULL, '', _t('文章推送邮件模板'), _t('自定义文章推送邮件的样式和内容'));
        $templateGroup->input->setAttribute('style', 'display:none');
        $form->addInput($templateGroup);

        // 邮件主题模板
        $emailSubjectTemplate = new Typecho_Widget_Helper_Form_Element_Text('emailSubjectTemplate', NULL, '[{siteName}] {articleCount}篇精选文章推荐', _t('邮件主题模板'), _t('可用变量：{siteName}网站名称, {articleCount}文章数量, {date}当前日期'));
        $form->addInput($emailSubjectTemplate);

        // 邮件头部模板
        $emailHeaderTemplate = new Typecho_Widget_Helper_Form_Element_Textarea('emailHeaderTemplate', NULL, 
'<div class="email-header" style="background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%); padding: 48px 32px; text-align: center; border-radius: 24px 24px 0 0; position: relative; overflow: hidden;">
    <div style="position: absolute; top: -50px; right: -50px; width: 100px; height: 100px; background: rgba(255,255,255,0.1); border-radius: 50%;"></div>
    <div style="position: absolute; bottom: -30px; left: -30px; width: 60px; height: 60px; background: rgba(255,255,255,0.08); border-radius: 50%;"></div>
    <div style="position: relative; z-index: 1;">
        <div style="width: 64px; height: 64px; background: rgba(255,255,255,0.2); border-radius: 20px; margin: 0 auto 16px; display: flex; align-items: center; justify-content: center; font-size: 28px;">📬</div>
        <h1 style="margin: 0 0 8px 0; color: white; font-size: 28px; font-weight: 800; letter-spacing: -0.02em;">{siteName}</h1>
        <p style="margin: 0; color: rgba(255,255,255,0.9); font-size: 16px; font-weight: 500;">为您精选 {articleCount} 篇优质文章</p>
    </div>
</div>', _t('邮件头部模板'), _t('可用变量：{siteName}网站名称, {articleCount}文章数量'));
        $emailHeaderTemplate->input->setAttribute('rows', '8');
        $form->addInput($emailHeaderTemplate);

        // 文章项目模板
        $articleItemTemplate = new Typecho_Widget_Helper_Form_Element_Textarea('articleItemTemplate', NULL,
'<div class="article-item" style="background: #ffffff; border-radius: 16px; padding: 32px; margin-bottom: 24px; box-shadow: 0 4px 20px rgba(0,0,0,0.04); border: 1px solid #e8ecef;">
    <div class="article-header" style="margin-bottom: 20px;">
        <h2 style="margin: 0 0 12px 0; font-size: 22px; font-weight: 700; color: #1a202c; line-height: 1.3;">
            <a href="{articleLink}" style="color: #1a202c; text-decoration: none;">{articleTitle}</a>
        </h2>
        <div class="article-meta" style="display: flex; align-items: center; gap: 16px; font-size: 14px; color: #718096; font-weight: 500;">
            <span>{articleAuthor}</span>
            <span>{articleDate}</span>
        </div>
    </div>
    <div class="article-summary" style="color: #4a5568; line-height: 1.7; margin-bottom: 24px; font-size: 15px;">
        {articleSummary}
    </div>
    <div class="article-footer">
        <a href="{articleLink}" style="display: inline-flex; align-items: center; gap: 8px; background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%); color: white; padding: 12px 24px; border-radius: 12px; text-decoration: none; font-size: 14px; font-weight: 600;">
            阅读全文 →
        </a>
    </div>
</div>', _t('文章项目模板'), _t('可用变量：{articleTitle}标题, {articleLink}链接, {articleAuthor}作者, {articleDate}日期, {articleSummary}摘要'));
        $articleItemTemplate->input->setAttribute('rows', '10');
        $form->addInput($articleItemTemplate);

        // 邮件底部模板
        $emailFooterTemplate = new Typecho_Widget_Helper_Form_Element_Textarea('emailFooterTemplate', NULL,
'<div class="email-footer" style="padding: 32px; text-align: center;">
    <div style="background: #ffffff; padding: 32px; border-radius: 20px; box-shadow: 0 4px 20px rgba(0,0,0,0.04); border: 1px solid #e8ecef;">
        <div style="width: 48px; height: 48px; background: linear-gradient(135deg, #f7fafc 0%, #edf2f7 100%); border-radius: 16px; margin: 0 auto 20px; display: flex; align-items: center; justify-content: center; font-size: 20px;">💌</div>
        <p style="margin: 0 0 16px 0; color: #4a5568; font-size: 15px; line-height: 1.6; font-weight: 500;">
            感谢您的订阅！如果您不想再收到此类邮件
        </p>
        <p style="margin: 0 0 24px 0;">
            <a href="{unsubscribeLink}" style="color: #4f46e5; text-decoration: none; font-weight: 600;">点击退订</a>
        </p>
        <div style="height: 1px; background: linear-gradient(90deg, transparent 0%, #e2e8f0 50%, transparent 100%); margin: 24px 0;"></div>
        <p style="margin: 0; color: #a0aec0; font-size: 13px; font-weight: 500;">
            © {currentYear} {siteName} · 发送时间：{currentDateTime}
        </p>
    </div>
</div>', _t('邮件底部模板'), _t('可用变量：{siteName}网站名称, {unsubscribeLink}退订链接, {currentYear}当前年份, {currentDateTime}当前时间'));
        $emailFooterTemplate->input->setAttribute('rows', '8');
        $form->addInput($emailFooterTemplate);

        // 邮件整体样式
        $emailStyleTemplate = new Typecho_Widget_Helper_Form_Element_Textarea('emailStyleTemplate', NULL,
'<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>{emailSubject}</title>
</head>
<body style="margin: 0; padding: 0; background: linear-gradient(135deg, #f7fafc 0%, #edf2f7 100%); font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', system-ui, Roboto, \'Helvetica Neue\', Arial, sans-serif; min-height: 100vh;">
    <div class="email-container" style="max-width: 640px; margin: 0 auto; background: transparent;">
        <div style="height: 40px;"></div>
        {emailHeader}
        <div class="email-content" style="background: #ffffff; padding: 40px 32px; border-radius: 0 0 24px 24px; box-shadow: 0 8px 32px rgba(0,0,0,0.06);">
            {emailContent}
        </div>
        {emailFooter}
        <div style="height: 40px;"></div>
    </div>
</body>
</html>', _t('邮件整体模板'), _t('可用变量：{emailSubject}邮件主题, {emailHeader}邮件头部, {emailContent}邮件内容, {emailFooter}邮件底部'));
        $emailStyleTemplate->input->setAttribute('rows', '12');
        $form->addInput($emailStyleTemplate);
    }

    /**
     * 个人配置
     */
    public static function personalConfig(Typecho_Widget_Helper_Form $form)
    {
        // 留空
    }

    /**
     * 创建数据库表
     */
    private static function createTable()
    {
        $db = Typecho_Db::get();
        $prefix = $db->getPrefix();
        
        // 订阅者表
        $sql = "CREATE TABLE IF NOT EXISTS `{$prefix}subscribers` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `email` varchar(255) NOT NULL,
            `name` varchar(100) DEFAULT '',
            `status` tinyint(1) DEFAULT 1 COMMENT '1:订阅 0:退订',
            `subscribe_time` datetime DEFAULT CURRENT_TIMESTAMP,
            `unsubscribe_time` datetime DEFAULT NULL,
            `token` varchar(32) NOT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `email` (`email`),
            KEY `status` (`status`)
        ) ENGINE=MyISAM DEFAULT CHARSET=utf8;";
        
        $db->query($sql);

        // 发送记录表
        $sql2 = "CREATE TABLE IF NOT EXISTS `{$prefix}send_log` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `article_ids` text NOT NULL COMMENT '文章ID列表，逗号分隔',
            `subscriber_ids` text NOT NULL COMMENT '订阅者ID列表，逗号分隔',
            `subject` varchar(255) NOT NULL,
            `send_time` datetime DEFAULT CURRENT_TIMESTAMP,
            `status` tinyint(1) DEFAULT 1 COMMENT '1:成功 0:失败',
            `error_msg` text,
            PRIMARY KEY (`id`),
            KEY `send_time` (`send_time`)
        ) ENGINE=MyISAM DEFAULT CHARSET=utf8;";
        
        $db->query($sql2);

        // 活跃用户统计表
        $sql3 = "CREATE TABLE IF NOT EXISTS `{$prefix}subscriber_stats` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `date` date NOT NULL,
            `active_count` int(11) DEFAULT 0,
            `new_count` int(11) DEFAULT 0,
            `unsubscribe_count` int(11) DEFAULT 0,
            PRIMARY KEY (`id`),
            UNIQUE KEY `date` (`date`)
        ) ENGINE=MyISAM DEFAULT CHARSET=utf8;";
        
        $db->query($sql3);
    }

    /**
     * 发送订阅确认邮件
     */
    public static function sendSubscribeNotification($email, $name = '')
    {
        $options = Helper::options();
        $pluginOptions = $options->plugin('Subscribe');
        
        if (!$pluginOptions->smtpHost) {
            return false;
        }

        require_once __DIR__ . '/lib/PHPMailer/PHPMailer.php';
        require_once __DIR__ . '/lib/PHPMailer/SMTP.php';
        require_once __DIR__ . '/lib/PHPMailer/Exception.php';

        try {
            $mail = new PHPMailer\PHPMailer\PHPMailer(true);
            
            // SMTP配置
            $mail->isSMTP();
            $mail->Host = $pluginOptions->smtpHost;
            $mail->SMTPAuth = true;
            $mail->Username = $pluginOptions->smtpUser;
            $mail->Password = $pluginOptions->smtpPass;
            $mail->SMTPSecure = $pluginOptions->smtpSecure;
            $mail->Port = intval($pluginOptions->smtpPort);
            $mail->CharSet = 'UTF-8';

            $mail->setFrom($pluginOptions->smtpUser, $pluginOptions->fromName ?: $options->title);
            $mail->addAddress($email, $name);

            $mail->isHTML(true);
            $mail->Subject = $pluginOptions->subscribeSubject ?: '感谢您的订阅！';
            
            $content = $pluginOptions->subscribeContent ?: '感谢您订阅我们的文章推送！我们会定期为您推送优质内容。';
            $mail->Body = self::generateNotificationTemplate($content, $options->title, $name);

            $mail->send();
            return true;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * 发送退订提醒邮件
     */
    public static function sendUnsubscribeNotification($email, $name = '')
    {
        $options = Helper::options();
        $pluginOptions = $options->plugin('Subscribe');
        
        if (!$pluginOptions->smtpHost) {
            return false;
        }

        require_once __DIR__ . '/lib/PHPMailer/PHPMailer.php';
        require_once __DIR__ . '/lib/PHPMailer/SMTP.php';
        require_once __DIR__ . '/lib/PHPMailer/Exception.php';

        try {
            $mail = new PHPMailer\PHPMailer\PHPMailer(true);
            
            // SMTP配置
            $mail->isSMTP();
            $mail->Host = $pluginOptions->smtpHost;
            $mail->SMTPAuth = true;
            $mail->Username = $pluginOptions->smtpUser;
            $mail->Password = $pluginOptions->smtpPass;
            $mail->SMTPSecure = $pluginOptions->smtpSecure;
            $mail->Port = intval($pluginOptions->smtpPort);
            $mail->CharSet = 'UTF-8';

            $mail->setFrom($pluginOptions->smtpUser, $pluginOptions->fromName ?: $options->title);
            $mail->addAddress($email, $name);

            $mail->isHTML(true);
            $mail->Subject = $pluginOptions->unsubscribeSubject ?: '希望您能再次回来！';
            
            $content = $pluginOptions->unsubscribeContent ?: '很遗憾您选择了退订。如果是因为邮件频率或内容问题，欢迎随时联系我们。希望您能再次回来！';
            $resubscribeLink = $options->siteUrl . '/?subscribe=1';
            $mail->Body = self::generateNotificationTemplate($content, $options->title, $name, $resubscribeLink);

            $mail->send();
            return true;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * 生成通知邮件模板（用于订阅/退订通知）
     */
    private static function generateNotificationTemplate($content, $siteName, $name = '', $resubscribeLink = '')
    {
        $greeting = $name ? "亲爱的 {$name}" : '亲爱的朋友';
        
        $resubscribeHtml = '';
        if ($resubscribeLink) {
            $resubscribeHtml = '<div style="margin-top: 20px; text-align: center;">
                <a href="' . $resubscribeLink . '" style="display: inline-block; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 12px 24px; border-radius: 25px; text-decoration: none; font-weight: 500;">
                    重新订阅
                </a>
            </div>';
        }

        return '
        <!DOCTYPE html>
        <html lang="zh-CN">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>' . htmlspecialchars($siteName) . '</title>
        </head>
        <body style="margin: 0; padding: 0; background: #f8fafc; font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, \'Helvetica Neue\', Arial, sans-serif;">
            <div style="max-width: 600px; margin: 40px auto; background: white; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 20px rgba(0,0,0,0.1);">
                
                <div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 40px 30px; text-align: center;">
                    <h1 style="margin: 0; color: white; font-size: 24px; font-weight: 700;">📬 ' . htmlspecialchars($siteName) . '</h1>
                </div>
                
                <div style="padding: 40px 30px;">
                    <h2 style="margin: 0 0 20px 0; color: #1a1a1a; font-size: 20px;">' . $greeting . '，</h2>
                    
                    <div style="color: #4a4a4a; line-height: 1.6; font-size: 16px; margin-bottom: 30px;">
                        ' . nl2br(htmlspecialchars($content)) . '
                    </div>
                    
                    ' . $resubscribeHtml . '
                </div>
                
                <div style="background: #f8fafc; padding: 20px 30px; text-align: center; border-top: 1px solid #e2e8f0;">
                    <p style="margin: 0; color: #64748b; font-size: 14px;">
                        © ' . date('Y') . ' ' . htmlspecialchars($siteName) . ' | ' . date('Y-m-d H:i:s') . '
                    </p>
                </div>
                
            </div>
        </body>
        </html>';
    }

    /**
     * 生成邮件模板 - 使用自定义模板（用于文章推送）
     */
    private static function generateEmailTemplate($articles, $siteName, $unsubscribeLink)
    {
        $options = Helper::options();
        $pluginOptions = $options->plugin('Subscribe');

        // 获取模板配置，如果没有配置则使用默认模板
        $subjectTemplate = $pluginOptions->emailSubjectTemplate ?: '[{siteName}] {articleCount}篇精选文章推荐';
        $headerTemplate = $pluginOptions->emailHeaderTemplate ?: self::getDefaultHeaderTemplate();
        $itemTemplate = $pluginOptions->articleItemTemplate ?: self::getDefaultItemTemplate();
        $footerTemplate = $pluginOptions->emailFooterTemplate ?: self::getDefaultFooterTemplate();
        $styleTemplate = $pluginOptions->emailStyleTemplate ?: self::getDefaultStyleTemplate();

        // 生成文章列表HTML
        $articlesHtml = '';
        foreach ($articles as $article) {
            $articleHtml = $itemTemplate;
            $articleHtml = str_replace('{articleTitle}', htmlspecialchars($article['title']), $articleHtml);
            $articleHtml = str_replace('{articleLink}', htmlspecialchars($article['link']), $articleHtml);
            $articleHtml = str_replace('{articleAuthor}', htmlspecialchars($article['author']), $articleHtml);
            $articleHtml = str_replace('{articleDate}', date('m月d日 H:i', strtotime($article['created'])), $articleHtml);
            $articleHtml = str_replace('{articleSummary}', htmlspecialchars($article['summary']), $articleHtml);
            
            $articlesHtml .= $articleHtml;
        }

        // 替换头部模板变量
        $emailHeader = $headerTemplate;
        $emailHeader = str_replace('{siteName}', htmlspecialchars($siteName), $emailHeader);
        $emailHeader = str_replace('{articleCount}', count($articles), $emailHeader);

        // 替换底部模板变量
        $emailFooter = $footerTemplate;
        $emailFooter = str_replace('{siteName}', htmlspecialchars($siteName), $emailFooter);
        $emailFooter = str_replace('{unsubscribeLink}', $unsubscribeLink, $emailFooter);
        $emailFooter = str_replace('{currentYear}', date('Y'), $emailFooter);
        $emailFooter = str_replace('{currentDateTime}', date('Y-m-d H:i'), $emailFooter);

        // 生成邮件主题
        $emailSubject = $subjectTemplate;
        $emailSubject = str_replace('{siteName}', $siteName, $emailSubject);
        $emailSubject = str_replace('{articleCount}', count($articles), $emailSubject);
        $emailSubject = str_replace('{date}', date('Y-m-d'), $emailSubject);

        // 组装最终邮件
        $finalEmail = $styleTemplate;
        $finalEmail = str_replace('{emailSubject}', htmlspecialchars($emailSubject), $finalEmail);
        $finalEmail = str_replace('{emailHeader}', $emailHeader, $finalEmail);
        $finalEmail = str_replace('{emailContent}', $articlesHtml, $finalEmail);
        $finalEmail = str_replace('{emailFooter}', $emailFooter, $finalEmail);

        return $finalEmail;
    }

    /**
     * 获取默认头部模板
     */
    private static function getDefaultHeaderTemplate()
    {
        return '<div class="email-header" style="background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%); padding: 48px 32px; text-align: center; border-radius: 24px 24px 0 0; position: relative; overflow: hidden;">
            <div style="position: absolute; top: -50px; right: -50px; width: 100px; height: 100px; background: rgba(255,255,255,0.1); border-radius: 50%;"></div>
            <div style="position: absolute; bottom: -30px; left: -30px; width: 60px; height: 60px; background: rgba(255,255,255,0.08); border-radius: 50%;"></div>
            <div style="position: relative; z-index: 1;">
                <div style="width: 64px; height: 64px; background: rgba(255,255,255,0.2); border-radius: 20px; margin: 0 auto 16px; display: flex; align-items: center; justify-content: center; font-size: 28px;">📬</div>
                <h1 style="margin: 0 0 8px 0; color: white; font-size: 28px; font-weight: 800; letter-spacing: -0.02em;">{siteName}</h1>
                <p style="margin: 0; color: rgba(255,255,255,0.9); font-size: 16px; font-weight: 500;">为您精选 {articleCount} 篇优质文章</p>
            </div>
        </div>';
    }

    /**
     * 获取默认文章项目模板
     */
    private static function getDefaultItemTemplate()
    {
        return '<div class="article-item" style="background: #ffffff; border-radius: 16px; padding: 32px; margin-bottom: 24px; box-shadow: 0 4px 20px rgba(0,0,0,0.04); border: 1px solid #e8ecef;">
            <div class="article-header" style="margin-bottom: 20px;">
                <h2 style="margin: 0 0 12px 0; font-size: 22px; font-weight: 700; color: #1a202c; line-height: 1.3;">
                    <a href="{articleLink}" style="color: #1a202c; text-decoration: none;">{articleTitle}</a>
                </h2>
                <div class="article-meta" style="display: flex; align-items: center; gap: 16px; font-size: 14px; color: #718096; font-weight: 500;">
                    <span>{articleAuthor}</span>
                    <span>{articleDate}</span>
                </div>
            </div>
            <div class="article-summary" style="color: #4a5568; line-height: 1.7; margin-bottom: 24px; font-size: 15px;">
                {articleSummary}
            </div>
            <div class="article-footer">
                <a href="{articleLink}" style="display: inline-flex; align-items: center; gap: 8px; background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%); color: white; padding: 12px 24px; border-radius: 12px; text-decoration: none; font-size: 14px; font-weight: 600;">
                    阅读全文 →
                </a>
            </div>
        </div>';
    }

    /**
     * 获取默认底部模板
     */
    private static function getDefaultFooterTemplate()
    {
        return '<div class="email-footer" style="padding: 32px; text-align: center;">
            <div style="background: #ffffff; padding: 32px; border-radius: 20px; box-shadow: 0 4px 20px rgba(0,0,0,0.04); border: 1px solid #e8ecef;">
                <div style="width: 48px; height: 48px; background: linear-gradient(135deg, #f7fafc 0%, #edf2f7 100%); border-radius: 16px; margin: 0 auto 20px; display: flex; align-items: center; justify-content: center; font-size: 20px;">💌</div>
                <p style="margin: 0 0 16px 0; color: #4a5568; font-size: 15px; line-height: 1.6; font-weight: 500;">
                    感谢您的订阅！如果您不想再收到此类邮件
                </p>
                <p style="margin: 0 0 24px 0;">
                    <a href="{unsubscribeLink}" style="color: #4f46e5; text-decoration: none; font-weight: 600;">点击退订</a>
                </p>
                <div style="height: 1px; background: linear-gradient(90deg, transparent 0%, #e2e8f0 50%, transparent 100%); margin: 24px 0;"></div>
                <p style="margin: 0; color: #a0aec0; font-size: 13px; font-weight: 500;">
                    © {currentYear} {siteName} · 发送时间：{currentDateTime}
                </p>
            </div>
        </div>';
    }

    /**
     * 获取默认整体样式模板
     */
    private static function getDefaultStyleTemplate()
    {
        return '<!DOCTYPE html>
        <html lang="zh-CN">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <meta http-equiv="X-UA-Compatible" content="IE=edge">
            <title>{emailSubject}</title>
        </head>
        <body style="margin: 0; padding: 0; background: linear-gradient(135deg, #f7fafc 0%, #edf2f7 100%); font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', system-ui, Roboto, \'Helvetica Neue\', Arial, sans-serif; min-height: 100vh;">
            <div class="email-container" style="max-width: 640px; margin: 0 auto; background: transparent;">
                <div style="height: 40px;"></div>
                {emailHeader}
                <div class="email-content" style="background: #ffffff; padding: 40px 32px; border-radius: 0 0 24px 24px; box-shadow: 0 8px 32px rgba(0,0,0,0.06);">
                    {emailContent}
                </div>
                {emailFooter}
                <div style="height: 40px;"></div>
            </div>
        </body>
        </html>';
    }

    /**
     * 更新统计数据
     */
    public static function updateStats()
    {
        $db = Typecho_Db::get();
        $prefix = $db->getPrefix();
        $today = date('Y-m-d');
        
        // 获取今日活跃订阅者数量
        $activeCount = $db->fetchObject($db->select(['COUNT(*)' => 'count'])
            ->from($prefix . 'subscribers')
            ->where('status = ?', 1))->count;
        
        // 获取今日新增订阅者数量
        $newCount = $db->fetchObject($db->select(['COUNT(*)' => 'count'])
            ->from($prefix . 'subscribers')
            ->where('DATE(subscribe_time) = ?', $today))->count;
        
        // 获取今日退订数量
        $unsubscribeCount = $db->fetchObject($db->select(['COUNT(*)' => 'count'])
            ->from($prefix . 'subscribers')
            ->where('DATE(unsubscribe_time) = ?', $today))->count;
        
        // 插入或更新统计数据
        $exists = $db->fetchRow($db->select()->from($prefix . 'subscriber_stats')->where('date = ?', $today));
        
        if ($exists) {
            $db->query($db->update($prefix . 'subscriber_stats')
                ->rows([
                    'active_count' => $activeCount,
                    'new_count' => $newCount,
                    'unsubscribe_count' => $unsubscribeCount
                ])
                ->where('date = ?', $today));
        } else {
            $db->query($db->insert($prefix . 'subscriber_stats')
                ->rows([
                    'date' => $today,
                    'active_count' => $activeCount,
                    'new_count' => $newCount,
                    'unsubscribe_count' => $unsubscribeCount
                ]));
        }
    }

    /**
     * 发送邮件给指定订阅者
     */
    public static function sendMailToSubscribers($articleIds, $subscriberIds, $customSubject = '')
    {
        require_once __DIR__ . '/lib/PHPMailer/PHPMailer.php';
        require_once __DIR__ . '/lib/PHPMailer/SMTP.php';
        require_once __DIR__ . '/lib/PHPMailer/Exception.php';

        $db = Typecho_Db::get();
        $prefix = $db->getPrefix();
        $options = Helper::options();
        $pluginOptions = $options->plugin('Subscribe');

        // 获取文章信息
        $articles = [];
        foreach ($articleIds as $articleId) {
            $article = $db->fetchRow($db->select()->from($prefix . 'contents')
                ->where('cid = ? AND status = ?', $articleId, 'publish'));
            
            if ($article) {
                // 获取文章链接
                $articleUrl = Typecho_Router::url('post', $article, $options->index);
                
                $articles[] = [
                    'title' => $article['title'],
                    'text' => $article['text'],
                    'summary' => self::getArticleSummary($article['text'], 200),
                    'created' => date('Y-m-d H:i:s', $article['created']),
                    'link' => $articleUrl,
                    'author' => self::getAuthorName($article['authorId'])
                ];
            }
        }

        if (empty($articles)) {
            return ['success' => false, 'message' => '没有找到有效的文章'];
        }

        // 获取订阅者信息
        $subscribers = [];
        foreach ($subscriberIds as $subscriberId) {
            $subscriber = $db->fetchRow($db->select()->from($prefix . 'subscribers')
                ->where('id = ? AND status = ?', $subscriberId, 1));
            
            if ($subscriber) {
                $subscribers[] = $subscriber;
            }
        }

        if (empty($subscribers)) {
            return ['success' => false, 'message' => '没有找到有效的订阅者'];
        }

        // 生成邮件主题
        if ($customSubject) {
            $subject = $customSubject;
        } else {
            $subjectTemplate = $pluginOptions->emailSubjectTemplate ?: '[{siteName}] {articleCount}篇精选文章推荐';
            $subject = str_replace('{siteName}', $options->title, $subjectTemplate);
            $subject = str_replace('{articleCount}', count($articles), $subject);
            $subject = str_replace('{date}', date('Y-m-d'), $subject);
        }

        $successCount = 0;
        $errorMessages = [];

        foreach ($subscribers as $subscriber) {
            try {
                $mail = new PHPMailer\PHPMailer\PHPMailer(true);
                
                // SMTP配置
                $mail->isSMTP();
                $mail->Host = $pluginOptions->smtpHost;
                $mail->SMTPAuth = true;
                $mail->Username = $pluginOptions->smtpUser;
                $mail->Password = $pluginOptions->smtpPass;
                $mail->SMTPSecure = $pluginOptions->smtpSecure;
                $mail->Port = intval($pluginOptions->smtpPort);
                $mail->CharSet = 'UTF-8';
                $mail->Timeout = 10;
                $mail->SMTPOptions = array(
                    'ssl' => array(
                        'verify_peer' => false,
                        'verify_peer_name' => false,
                        'allow_self_signed' => true
                    )
                );

                // 发件人
                $mail->setFrom($pluginOptions->smtpUser, $pluginOptions->fromName ?: $options->title);
                
                // 收件人
                $mail->addAddress($subscriber['email'], $subscriber['name']);

                // 邮件内容
                $unsubscribeLink = $options->siteUrl . '/action/subscribe?do=unsubscribe&token=' . $subscriber['token'];
                
                $mailContent = self::generateEmailTemplate($articles, $options->title, $unsubscribeLink);

                $mail->isHTML(true);
                $mail->Subject = $subject;
                $mail->Body = $mailContent;

                $mail->send();
                $successCount++;
                
            } catch (Exception $e) {
                $errorMessages[] = $subscriber['email'] . ': ' . $e->getMessage();
            }
        }

        // 记录发送日志
        $logData = [
            'article_ids' => implode(',', $articleIds),
            'subscriber_ids' => implode(',', $subscriberIds),
            'subject' => $subject,
            'status' => $successCount > 0 ? 1 : 0,
            'error_msg' => implode('; ', $errorMessages)
        ];
        
        $db->query($db->insert($prefix . 'send_log')->rows($logData));

        if ($successCount == count($subscribers)) {
            return ['success' => true, 'message' => "邮件发送成功，共发送给 {$successCount} 位订阅者"];
        } else {
            return ['success' => false, 'message' => "部分发送失败，成功 {$successCount}/" . count($subscribers) . "，错误：" . implode('; ', $errorMessages)];
        }
    }

    /**
     * 获取文章摘要
     */
    private static function getArticleSummary($text, $length = 200)
    {
        // 移除HTML标签和Markdown语法
        $text = strip_tags($text);
        $text = preg_replace('/\[.*?\]/', '', $text); // 移除链接
        $text = preg_replace('/[#*`>-]/', '', $text); // 移除Markdown符号
        $text = preg_replace('/\s+/', ' ', $text); // 合并空白字符
        $text = trim($text);
        
        if (mb_strlen($text, 'UTF-8') > $length) {
            return mb_substr($text, 0, $length, 'UTF-8') . '...';
        }
        return $text;
    }

    /**
     * 获取作者名称
     */
    private static function getAuthorName($authorId)
    {
        $db = Typecho_Db::get();
        $prefix = $db->getPrefix();
        
        $author = $db->fetchRow($db->select('screenName')->from($prefix . 'users')->where('uid = ?', $authorId));
        return $author ? $author['screenName'] : '未知作者';
    }
}
