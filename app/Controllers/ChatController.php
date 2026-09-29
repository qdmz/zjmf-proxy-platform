<?php
namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\DB;

/** 在线客服（规则机器人 + 转人工工单） */
class ChatController extends Controller
{
protected function sessionKey(): string
{
if (empty($_SESSION['chat_key'])) {
$_SESSION['chat_key'] = bin2hex(random_bytes(16));
}
return $_SESSION['chat_key'];
}

/** 发送消息，返回机器人回复（JSON） */
public function send(): void
{
$msg = trim($_POST['message']?? '');
if ($msg === '') {
json_fail('消息不能为空');
}
$msg = mb_substr($msg, 0, 500);
$key = $this->sessionKey();
$user = Auth::user();

DB::insert('chat_messages', [
'session_key' => $key,
'user_id' => $user? (int)$user['id']: null,
'sender' => 'user',
'message' => $msg,
]);

$reply = chatbot_reply($msg);

DB::insert('chat_messages', [
'session_key' => $key,
'user_id' => $user? (int)$user['id']: null,
'sender' => 'bot',
'message' => $reply,
]);

// 只保留最近 50 条
DB::query(
"DELETE FROM `chat_messages` WHERE `session_key` =? AND `id` NOT IN (
SELECT `id` FROM (SELECT `id` FROM `chat_messages` WHERE `session_key` =? ORDER BY `id` DESC LIMIT 50) t
)",
[$key, $key]
);

json_ok(['reply' => $reply]);
}

/** 转人工：将会话记录生成工单 */
public function transfer(): void
{
$user = Auth::user();
if (!$user) {
json_fail('请先登录后再转人工客服', 401, ['login' => true]);
}
$key = $this->sessionKey();
$msgs = DB::all(
"SELECT `sender`, `message`, `created_at` FROM `chat_messages`
WHERE `session_key` =? ORDER BY `id` DESC LIMIT 20",
[$key]
);
$transcript = "\n";
foreach (array_reverse($msgs) as $m) {
$who = $m['sender'] === 'user'? '用户': ($m['sender'] === 'bot'? '机器人': '客服');
$transcript.= "[{$m['created_at']}] {$who}：{$m['message']}\n";
}
$tid = DB::insert('tickets', [
'user_id' => (int)$user['id'],
'title' => '在线客服转人工咨询',
'status' => 'open',
]);
DB::insert('ticket_replies', [
'ticket_id' => $tid,
'user_id' => (int)$user['id'],
'is_admin' => 0,
'content' => $transcript,
]);
// 清空会话，避免重复转接
DB::delete('chat_messages', '`session_key` =:k', ['k' => $key]);
json_ok(['ticket_id' => (int)$tid], '已为您转接人工客服，工单 #'. $tid. ' 已创建');
}
}
