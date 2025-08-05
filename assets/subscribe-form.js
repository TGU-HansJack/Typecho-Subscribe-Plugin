// subscribe-form.js
(function() {
    'use strict';
    
    // 获取插件URL
    function getPluginUrl() {
        var scripts = document.getElementsByTagName('script');
        for (var i = 0; i < scripts.length; i++) {
            var src = scripts[i].src;
            if (src && src.indexOf('subscribe-form.js') > -1) {
                return src.replace('/assets/subscribe-form.js', '');
            }
        }
        // fallback
        return '/usr/plugins/Subscribe';
    }
    
    var pluginUrl = getPluginUrl();
    
    // 配置
    var config = {
        apiUrl: '/action/subscribe',
        pluginUrl: pluginUrl,
        formId: 'subscribe-form-' + Date.now(),
        emailInputId: 'subscribe-email-' + Date.now(),
        nameInputId: 'subscribe-name-' + Date.now(),
        submitBtnId: 'subscribe-submit-' + Date.now()
    };
    
    // 加载CSS
    function loadCSS() {
        var cssId = 'subscribe-form-css';
        if (!document.getElementById(cssId)) {
            var link = document.createElement('link');
            link.id = cssId;
            link.rel = 'stylesheet';
            link.type = 'text/css';
            link.href = config.pluginUrl + '/assets/subscribe-form.css';
            document.head.appendChild(link);
        }
    }
    
    // 创建订阅表单HTML
    function createSubscribeForm() {
        return `
        <div class="subscribe-widget">
            <div class="subscribe-header">
                <h3>📬 订阅我们</h3>
                <p>获取最新文章推送，不错过精彩内容</p>
            </div>
            
            <form id="${config.formId}" class="subscribe-form">
                <div class="form-group">
                    <input 
                        type="email" 
                        id="${config.emailInputId}"
                        name="email"
                        placeholder="请输入您的邮箱地址"
                        required
                    >
                </div>
                
                <div class="form-group">
                    <input 
                        type="text" 
                        id="${config.nameInputId}"
                        name="name"
                        placeholder="您的姓名（可选）"
                    >
                </div>
                
                <button type="submit" id="${config.submitBtnId}">
                    立即订阅 ✨
                </button>
            </form>
            
            <div class="subscribe-footer">
                <small>我们承诺不会发送垃圾邮件，您可以随时退订</small>
            </div>
        </div>`;
    }
    
    // 显示消息
    function showMessage(message, type) {
        type = type || 'info';
        
        var messageDiv = document.createElement('div');
        messageDiv.className = 'subscribe-message ' + type;
        messageDiv.textContent = message;
        
        document.body.appendChild(messageDiv);
        
        setTimeout(function() {
            if (messageDiv.parentNode) {
                messageDiv.style.animation = 'slideIn 0.3s ease-out reverse';
                setTimeout(function() {
                    if (messageDiv.parentNode) {
                        messageDiv.parentNode.removeChild(messageDiv);
                    }
                }, 300);
            }
        }, 3000);
    }
    
    // 处理表单提交
    function handleSubmit(event) {
        event.preventDefault();
        
        var form = event.target;
        var email = form.email.value.trim();
        var name = form.name.value.trim();
        var submitBtn = form.querySelector('button[type="submit"]');
        
        if (!email) {
            showMessage('请输入邮箱地址', 'error');
            return;
        }
        
        // 验证邮箱格式
        var emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        if (!emailRegex.test(email)) {
            showMessage('请输入有效的邮箱地址', 'error');
            return;
        }
        
        // 禁用提交按钮
        var originalText = submitBtn.textContent;
        submitBtn.disabled = true;
        submitBtn.textContent = '订阅中...';
        
        // 发送请求
        var formData = new FormData();
        formData.append('do', 'subscribe');
        formData.append('email', email);
        formData.append('name', name);
        
        fetch(config.apiUrl, {
            method: 'POST',
            body: formData
        })
        .then(function(response) {
            return response.json();
        })
        .then(function(data) {
            if (data.success) {
                showMessage(data.message, 'success');
                form.reset();
            } else {
                showMessage(data.message, 'error');
            }
        })
        .catch(function(error) {
            console.error('订阅失败:', error);
            showMessage('订阅失败，请稍后重试', 'error');
        })
        .finally(function() {
            // 恢复提交按钮
            submitBtn.disabled = false;
            submitBtn.textContent = originalText;
        });
    }
    
    // 初始化订阅表单
    function initSubscribeForm() {
        // 加载CSS
        loadCSS();
        
        // 查找所有需要插入订阅表单的容器
        var containers = document.querySelectorAll('.subscribe-form-container, [data-subscribe-form]');
        
        containers.forEach(function(container) {
            container.innerHTML = createSubscribeForm();
            
            // 绑定表单提交事件
            var form = container.querySelector('#' + config.formId);
            if (form) {
                form.addEventListener('submit', handleSubmit);
            }
        });
        
        // 如果URL包含subscribe参数，自动显示订阅表单
        var urlParams = new URLSearchParams(window.location.search);
        if (urlParams.get('subscribe') === '1') {
            showSubscribeModal();
        }
    }
    
    // 显示订阅模态框
    function showSubscribeModal() {
        // 加载CSS
        loadCSS();
        
        var modal = document.createElement('div');
        modal.className = 'subscribe-modal';
        
        var modalContent = document.createElement('div');
        modalContent.className = 'subscribe-modal-content';
        
        modalContent.innerHTML = `
            <button class="subscribe-modal-close">×</button>
            ${createSubscribeForm()}
        `;
        
        modal.appendChild(modalContent);
        document.body.appendChild(modal);
        
        // 绑定关闭事件
        var closeBtn = modal.querySelector('.subscribe-modal-close');
        closeBtn.addEventListener('click', function() {
            modal.remove();
        });
        
        // 绑定表单提交事件
        var form = modal.querySelector('#' + config.formId);
        if (form) {
            form.addEventListener('submit', handleSubmit);
        }
        
        // 点击背景关闭
        modal.addEventListener('click', function(e) {
            if (e.target === modal) {
                modal.remove();
            }
        });
    }
    
    // 页面加载完成后初始化
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initSubscribeForm);
    } else {
        initSubscribeForm();
    }
    
    // 暴露全局方法
    window.SubscribeForm = {
        init: initSubscribeForm,
        showModal: showSubscribeModal,
        config: config
    };
})();
