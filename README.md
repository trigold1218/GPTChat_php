# gpt_chat
# 面向GPT 编程 api
## 安装
```composer require trigold/gpt_chat ```

## 使用
```php
        $token ='your token';
        $chat =  new GPTChat($token);
        $chat->authenticate();
        $chat->send_message('你好你好');
