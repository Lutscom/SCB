<?php

use danog\MadelineProto\APIWrapper;
use danog\MadelineProto\Settings;
use danog\MadelineProto\Settings\AppInfo;
use danog\MadelineProto\API;
use danog\MadelineProto;

require_once 'madeline.php';

class SecretHandler extends \danog\MadelineProto\EventHandler
{
    private $sent = [-440592694 => true];
    public function __construct(?APIWrapper $API)
    {
        parent::__construct($API);
        $this->sent = [];
    }

    const ADMIN = "admin";

    public function getReportPeers()
    {
        return [self::ADMIN];
    }

    public function onUpdateNewMessage(array $update): \Generator
    {
        $send_str='';
        $j=0;
        $fp = fopen('list_secret.txt', 'a+');
        $list_secret = file('list_secret.txt');
        $order   = array("\r\n", "\n", "\r");
        $replace = '';
        foreach ($list_secret as $ls_elem) {
            $ls_elem = str_replace($order, $replace, $ls_elem);
            $list_secret[$j]=$ls_elem;
            $j++;
            $send_str = $send_str.$ls_elem."\r\n";
            }
        fclose($fp);

        if ($update['message']['message'] === 'referal' and ($update['message']['user_id']=="179017433" or $update['message']['user_id']=="393956396")){
            yield $this->messages->sendMessage(['message' => "Choose group secret chat for getting referal code:"."\r\n".$send_str, 'peer' => $update]);
        }

        if (preg_match('/create (.*)/',$update['message']['message'])=== 1 and ($update['message']['user_id']=="179017433" or $update['message']['user_id']=="393956396")){
            $chat_name=$update['message']['message'];
            $chat_name = str_replace("create ", '', $chat_name);
            $fp = fopen('list_secret.txt', 'a+');
            if (filesize('list_secret.txt')===0){
                fwrite($fp, $chat_name);
            }
            else{
                fwrite($fp, "\r\n".$chat_name);
            }
            clearstatcache();
            fclose($fp);
            yield $this->messages->sendMessage(['message' => $chat_name." group secret chat was created!", 'peer' => $update]);
        }
        
        if (in_array($update['message']['message'],$list_secret)===true /*and ($update['message']['user_id']=="179017433" or $update['message']['user_id']=="393956396"*/){
            $messages = yield $this->messages->getHistory(['peer' => $update, 'offset_id' => '0', 'offset_date' => '0', 'add_offset' => '0', 'limit' => '5', 'max_id' => '0', 'min_id' => '0' ]);
            $messages = $messages['messages'];
            if ($messages[2]['message'] === 'referal'){
                $rand = random_int(100000, 999999);
                yield $this->messages->sendMessage(['message' => (string)$rand, 'peer' => $update]);
                $fp = fopen('referal.txt', 'a+');
                $current_time=time();
                if (filesize('referal.txt')===0){
                    fwrite($fp, $rand.' '.$current_time.' '.$update['message']['message']);
                }
                else{
                    fwrite($fp, "\r\n".$rand.' '.$current_time.' '.$update['message']['message']);
                }
                clearstatcache();
                fclose($fp);
            }           
        }
        
        if (preg_match('/request ([1-9]{1})([0-9]{5})/',$update['message']['message'])=== 1){
            $current_time=time();
            $code=$update['message']['message'];
            $order = "request ";
            $replace = '';
            $code = str_replace("request ", '', $code);           
            if (filesize('referal.txt')===0){
                    yield $this->messages->sendMessage(['message' => "No active codes at this moment", 'peer' => $update]);
                    clearstatcache();
            }
            else{
                $send_str='';
                $j=0;
                $fp = fopen('referal.txt', 'a+');
                $referal = file('referal.txt');
                fclose($fp);
                $order   = array("\r\n", "\n", "\r");
                $replace = '';
                foreach ($referal as $rf_elem) {
                    $rf_elem = str_replace($order, $replace, $rf_elem);
                    $referal[$j]=$rf_elem;
                    $j++;
                }
                $ref_new=array();
                $i=0;
                foreach ($referal as $rf_elem) {
                    $k=0;
                    $ref_new[$i][$k]=substr($rf_elem, 0, 6);
                    $k++;
                    $ref_new[$i][$k]=substr($rf_elem, 7, 10);
                    $k++;
                    $ref_new[$i][$k]=substr($rf_elem, 18, strlen($rf_elem)-18);
                    $i++;
                }
                $check = false;
                foreach ($ref_new as $rf_elem){
                    if ($rf_elem[0]===$code){
                        $check=true;
                        $time=$rf_elem[1];
                        $chat=$rf_elem[2];
                        settype($time, "integer");
                        $time=$time+300;
                    }
                }
                if ($check == True){
                    if ($current_time<$time){
                        $secret_chat_id = yield $this->requestSecretChat($update);
                        $name = yield $this->getFullInfo($update);
                        if (isset($name['User']['last_name'])){
                            $name = $name['User']['first_name']." ".$name['User']['last_name'];
                        }
                        else{
                            $name = $name['User']['first_name'];
                        }
                        $fp = fopen('counter.txt', 'a+');
                        if (filesize('counter.txt')===0){
                            fwrite($fp, $secret_chat_id.'|'.$name.'|'.$chat);
                        }
                        else{
                            fwrite($fp, "\r\n".$secret_chat_id.'|'.$name.'|'.$chat);
                        }
                        clearstatcache();
                        fclose($fp);
                        yield $this->messages->sendMessage(['message' => "You have successfully entered a secret group chat!", 'peer' => $update]);
                        yield $this->messages->sendMessage(['message' => "Enter /members to your new secret chat to get full list of chat members", 'peer' => $update]);
                        yield $this->messages->sendMessage(['message' => "Enter /help to show available commands to manage chats", 'peer' => $update]);
                    }
                    else yield $this->messages->sendMessage(['message' => "Out of time for this code", 'peer' => $update]); 
                }
                else yield $this->messages->sendMessage(['message' => "This code is not valid", 'peer' => $update]);
                //delete out of time codes
            }
        }
    }

    public function onUpdateNewEncryptedMessage(array $update){
        print_r($update);
        if (isset($update['message']['decrypted_message']['media'])) {
            $media = yield $this->downloadToDir($update, '.');
            print_r($media);
            $send_str='';
            $j=0;
            $fp = fopen('counter.txt', 'a+');
            $counter = file('counter.txt');
            fclose($fp);
            $order   = array("\r\n", "\n", "\r");
            $replace = '';
            foreach ($counter as $ct_elem) {
                $ct_elem = str_replace($order, $replace, $ct_elem);
                $counter[$j]=$ct_elem;
                $j++;
            }
            $counter_new=array();
            $i=0;
            foreach ($counter as $ct_elem) {
                $k=0;
                $offset=0;
                $pos_1 = strpos($ct_elem, "|", $offset);
                $counter_new[$i][$k]=substr($ct_elem, 0, $pos_1);
                $k++;
                $offset=$pos_1+1;
                $pos_2=strpos($ct_elem, "|", $offset);
                $counter_new[$i][$k]=substr($ct_elem, $pos_1+1, $pos_2-$pos_1-1);
                $k++;
                $counter_new[$i][$k]=substr($ct_elem, $pos_2+1, strlen($ct_elem)-$pos_2-1);
                $i++;
            }
            foreach ($counter_new as $ct_elem) {
                if ($ct_elem[0] == $update['message']['chat_id']) {
                    $name = $ct_elem[1];
                    $chat = $ct_elem[2];
                    }          
            }
            foreach ($counter_new as $ct_elem) {
                if ($ct_elem[0] != $update['message']['chat_id']) {
                    if ($ct_elem[2] == $chat)
                        yield $this->messages->sendEncryptedFile(['peer' => $ct_elem[0], 'file' => $media, 'message' => ['_' => 'decryptedMessage', 'ttl' => 0, 'message' => '', 'media' => ['_' => 'decryptedMessageMediaPhoto', 'thumb' => \file_get_contents("file.jpg"), 'thumb_w' => 10, 'thumb_h' => 10, 'caption' => $name.":\r\n".$update['message']['decrypted_message']['message'], 'size' => \filesize($media), 'w' => 100, 'h' => 100]]]);
                }
            }
        }     
        else {
            $send_str='';
            $j=0;
            $fp = fopen('counter.txt', 'a+');
            $counter = file('counter.txt');
            fclose($fp);
            $order   = array("\r\n", "\n", "\r");
            $replace = '';
            foreach ($counter as $ct_elem) {
                $ct_elem = str_replace($order, $replace, $ct_elem);
                $counter[$j]=$ct_elem;
                $j++;
            }
            $counter_new=array();
            $i=0;
            foreach ($counter as $ct_elem) {
                $k=0;
                $offset=0;
                $pos_1 = strpos($ct_elem, "|", $offset);
                $counter_new[$i][$k]=substr($ct_elem, 0, $pos_1);
                $k++;
                $offset=$pos_1+1;
                $pos_2=strpos($ct_elem, "|", $offset);
                $counter_new[$i][$k]=substr($ct_elem, $pos_1+1, $pos_2-$pos_1-1);
                $k++;
                $counter_new[$i][$k]=substr($ct_elem, $pos_2+1, strlen($ct_elem)-$pos_2-1);
                $i++;
            }
            foreach ($counter_new as $ct_elem) {
                if ($ct_elem[0] == $update['message']['chat_id']) {
                    $name = $ct_elem[1];
                    $chat = $ct_elem[2];
                    }          
            }
            if ($update['message']['decrypted_message']['message'] == "/members"){
                $send_str='';
                foreach ($counter_new as $ct_elem) {
                    if ($ct_elem[0] != $update['message']['chat_id']) {
                        if ($ct_elem[2] == $chat) $send_str = $send_str.$ct_elem[1]."\r\n";
                    }
                }
                yield $this->messages->sendEncrypted(['peer' => $update['message']['chat_id'], 'message' => ['_' => 'decryptedMessage', 'ttl' => 0, 'message' => "Members of ".$chat." chat:"."\r\n".$send_str]]);
            }
            if ($update['message']['decrypted_message']['message'] == "/help"){
                yield $this->messages->sendEncrypted(['peer' => $update['message']['chat_id'], 'message' => ['_' => 'decryptedMessage', 'ttl' => 0, 'message' => "Available commands:"."\r\n"."1. create * - creat group secret chat, where * - chat name"."\r\n"."2. referal - show list of created group secret chats (next command should be chat name to creating referal code)"."\r\n"."3. request * - join group secret chat, * - your referal code (code is active for 5 minutes since ithave been generated)"]]);
            }
            else{
                foreach ($counter_new as $ct_elem) {
                    if ($ct_elem[0] != $update['message']['chat_id']) {
                        if ($ct_elem[2] == $chat)
                            yield $this->messages->sendEncrypted(['peer' => $ct_elem[0], 'message' => ['_' => 'decryptedMessage', 'ttl' => 0, 'message' => $name.":\r\n".$update['message']['decrypted_message']['message']]]);
                    }
                }
            }
        }
        if (isset($this->sent[$update['message']['chat_id']])) {
            return;
        }      
        $this->sent[$update['message']['chat_id']] = true;
    }
    
}
if (\file_exists('.env')) {
    echo 'Loading .env...'.PHP_EOL;
    $dotenv = Dotenv\Dotenv::create(\getcwd());
    $dotenv->load();
}

echo 'Loading settings...'.PHP_EOL;
$settings = \json_decode(\getenv('MTPROTO_SETTINGS'), true) ?: [];

$MadelineProto = new \danog\MadelineProto\API('secret.madeline', $settings);

$MadelineProto->startAndLoop(SecretHandler::class);