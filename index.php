<?php
// Render.com specific settings
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL & ~E_NOTICE);

// ================= CONFIG (Yahan settings daalo) =================
$BOT_TOKEN = "8315381064:AAGk0FGVGmB8j5SjpBvW3rD3_kQHe_hyOWU"; // Bot ka token
$BOT_API = "https://api.telegram.org/bot$BOT_TOKEN/";

$GROUP_ID = -1003083386043; // Group ID
$OWNER_ID = 1080317415;     // Bot Owner ID

// Storage files (bot ka record data yahan save hota)
$delete_file = "group_messages.json"; // Delete schedule store
$meta_file   = "delete_meta.json";    // Auto delete settings
$flood_file  = "flood_control.json";  // Flood/spam logs
$warn_file   = "warn_users.json";     // Warning system logs
$log_file    = "admin_log.txt";       // Admin logs file

// Auto delete default time (hours me)
$default_hours = 24;

// Gali filter words (bad words list)
$bad_words = ["fuck","bhosdke","bhenchod","bc","mc","randi","madarchod","chutiya","lund","gandu","sala","harami","porn","sex"];

// Agar files nahi to create karo
foreach ([
    $delete_file=>"{}", 
    $meta_file=>json_encode(["hours"=>$default_hours]), 
    $flood_file=>"{}", 
    $warn_file=>"{}"
] as $file=>$default){
    if(!file_exists($file)) {
        file_put_contents($file,$default);
        chmod($file, 0666);
    }
}

// JSON load karo with error handling
$messages = [];
if (file_exists($delete_file)) {
    $messages_data = file_get_contents($delete_file);
    $messages = json_decode($messages_data, true) ?: [];
}

$meta = [];
if (file_exists($meta_file)) {
    $meta_data = file_get_contents($meta_file);
    $meta = json_decode($meta_data, true) ?: [];
}

$flood = [];
if (file_exists($flood_file)) {
    $flood_data = file_get_contents($flood_file);
    $flood = json_decode($flood_data, true) ?: [];
}

$warns = [];
if (file_exists($warn_file)) {
    $warns_data = file_get_contents($warn_file);
    $warns = json_decode($warns_data, true) ?: [];
}

$delete_hours = $meta["hours"] ?? $default_hours;

// Telegram request function
function tg($method, $data) { 
    global $BOT_API; 
    $url = $BOT_API . $method;
    
    $options = [
        'http' => [
            'header'  => "Content-type: application/x-www-form-urlencoded\r\n",
            'method'  => 'POST',
            'content' => http_build_query($data),
        ],
    ];
    
    $context  = stream_context_create($options);
    $result = @file_get_contents($url, false, $context);
    
    return $result;
}

// Admin log + Owner ko message
function log_admin($msg){
    global $OWNER_ID, $log_file;
    
    // Log file mein write karo
    $log_entry = date("Y-m-d H:i:s") . " — " . $msg . "\n";
    @file_put_contents($log_file, $log_entry, FILE_APPEND);
    
    // Owner ko message bhejo
    tg("sendMessage", [
        "chat_id" => $OWNER_ID,
        "text" => "🛠 " . $msg
    ]);
}

// ============== Purane messages 24 hours baad delete ==============
if (is_array($messages) && count($messages) > 0) {
    $current_time = time();
    $messages_to_keep = [];
    
    foreach($messages as $mid => $info){
        if (isset($info["time"]) && isset($info["del"])) {
            if($current_time - $info["time"] < ($info["del"] * 3600)){
                $messages_to_keep[$mid] = $info;
            } else {
                // Message delete karo
                @tg("deleteMessage", [
                    "chat_id" => $GLOBALS["GROUP_ID"], 
                    "message_id" => $mid
                ]);
            }
        }
    }
    
    @file_put_contents($delete_file, json_encode($messages_to_keep));
    $messages = $messages_to_keep;
}

// Update data lo
$input = file_get_contents("php://input");
$update = json_decode($input, true);

// Agar koi update nahi aaya hai (direct URL access)
if(!$update) {
    // Health check response for Render.com
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        header('Content-Type: text/plain; charset=utf-8');
        echo "🤖 Telegram Bot is running on Render.com!\n";
        echo "✅ Bot Status: Online\n";
        echo "🕒 Server Time: " . date('Y-m-d H:i:s') . "\n";
        echo "📊 Messages in queue: " . count($messages) . "\n";
        echo "🌐 Webhook: Ready\n";
        echo "🚀 Bot is ready to receive messages!";
        exit;
    }
    
    // Agar POST request hai par invalid data
    http_response_code(200);
    echo "OK";
    exit;
}

// ================= MESSAGE HANDLER =================
if(isset($update["message"])){

    $message = $update["message"];
    $chat_id = $message["chat"]["id"];
    $user_id = $message["from"]["id"];
    $message_text = strtolower(trim($message["text"] ?? ""));
    $message_id = $message["message_id"];
    
    // User info
    $username = $message["from"]["username"] ?? "NoUsername";
    $first_name = $message["from"]["first_name"] ?? "Unknown";

    // Group messages ko delete queue me save karo
    if($chat_id == $GROUP_ID){
        $messages[$message_id] = [
            "time" => time(), 
            "del" => $delete_hours
        ];
        @file_put_contents($delete_file, json_encode($messages));
    }

    // ========== New Member joined ==========
    if(isset($message["new_chat_members"])){
        foreach($message["new_chat_members"] as $new_member){

            $name = $new_member["first_name"];
            $new_user_id = $new_member["id"];

            $welcome = "Hello $name 👋\nWelcome to the group! 🎀\n\n👇 Neeche wale buttons use karo 👇";

            $buttons = [
                "inline_keyboard" => [
                    [
                        ["text" => "📜 Rules", "callback_data" => "show_rules"],
                        ["text" => "🎬 Movie Request Guide", "callback_data" => "req_guide"]
                    ],
                    [
                        ["text" => "❤️ Support Bot", "url" => "https://t.me/EntertainmentTadkaBot"]
                    ]
                ]
            ];

            tg("sendMessage", [
                "chat_id" => $GROUP_ID,
                "text" => $welcome,
                "reply_markup" => json_encode($buttons),
                "parse_mode" => "HTML"
            ]);

            log_admin("✅ New user joined: $name (ID: $new_user_id, Username: @$username)");
        }
        exit;
    }

    // ========== Member left ==========
    if(isset($message["left_chat_member"])){
        $left_member = $message["left_chat_member"];
        $name = $left_member["first_name"];
        $left_user_id = $left_member["id"];
        
        tg("sendMessage", [
            "chat_id" => $GROUP_ID, 
            "text" => "👋 $name group chhod ke chala gaya.\nPhir milte hain ✨"
        ]);
        
        log_admin("🚪 User left: $name (ID: $left_user_id, Username: @$username)");
        exit;
    }

    // ========== Gali Filter (bad words block) ==========
    if($chat_id == $GROUP_ID && !empty($message_text)){
        foreach($bad_words as $bad_word){
            if(strpos($message_text, $bad_word) !== false){

                // Message delete karo
                tg("deleteMessage", [
                    "chat_id" => $GROUP_ID, 
                    "message_id" => $message_id
                ]);

                // Warning message bhejo
                tg("sendMessage", [
                    "chat_id" => $GROUP_ID,
                    "text" => "⚠️ @" . $username . " Gaali mat do bhai.\n2 minute ke liye mute kar diya 😐"
                ]);

                // User ko mute karo
                tg("restrictChatMember", [
                    "chat_id" => $GROUP_ID,
                    "user_id" => $user_id,
                    "permissions" => json_encode([
                        "can_send_messages" => false,
                        "can_send_media_messages" => false,
                        "can_send_other_messages" => false,
                        "can_add_web_page_previews" => false
                    ]),
                    "until_date" => time() + 120
                ]);

                log_admin("🚫 Bad word used by: $first_name (ID: $user_id, Username: @$username) - Word: $bad_word");
                exit;
            }
        }
    }

    // ========== Flood Control (spam check) ==========
    if($chat_id == $GROUP_ID){
        $current_time = time();
        
        // Flood control data initialize karo
        if(!isset($flood[$user_id])) {
            $flood[$user_id] = [];
        }
        
        // Current time add karo
        $flood[$user_id][] = $current_time;
        
        // 5 seconds se purane entries remove karo
        $flood[$user_id] = array_filter($flood[$user_id], function($timestamp) use ($current_time) {
            return ($current_time - $timestamp) <= 5;
        });
        
        // Agar 5 seconds mein 3 ya usse jyada messages hai
        if(count($flood[$user_id]) >= 3){

            // User ko mute karo
            tg("restrictChatMember", [
                "chat_id" => $GROUP_ID,
                "user_id" => $user_id,
                "permissions" => json_encode([
                    "can_send_messages" => false,
                    "can_send_media_messages" => false,
                    "can_send_other_messages" => false,
                    "can_add_web_page_previews" => false
                ]),
                "until_date" => $current_time + 60
            ]);

            // Warning message
            tg("sendMessage", [
                "chat_id" => $GROUP_ID, 
                "text" => "⚠️ @" . $username . " Spam mat karo, 1 min mute"
            ]);

            log_admin("🚫 Flood mute: $first_name (ID: $user_id, Username: @$username) - " . count($flood[$user_id]) . " messages in 5 seconds");
            
            // Flood data save karo
            @file_put_contents($flood_file, json_encode($flood));
            exit;
        }

        // Flood data save karo
        @file_put_contents($flood_file, json_encode($flood));
    }

    // ========== Commands Handler ==========

    // Start command
    if($message_text == "/start"){
        tg("sendMessage", [
            "chat_id" => $chat_id,
            "text" => "🤖 Bot Online Hai!\n🧹 Messages delete: $delete_hours hrs ke baad\nType /help",
            "parse_mode" => "HTML"
        ]); 
        exit;
    }

    // Help command
    if($message_text == "/help"){
        $help_text = "🤖 <b>Bot Commands</b>\n\n";
        $help_text .= "/start - Bot status check\n";
        $help_text .= "/help - Ye help message\n";
        $help_text .= "/rules - Group rules dekh\n";
        $help_text .= "/setdelete 24h - Auto delete time set (Owner only)\n";
        $help_text .= "/warn @username - User ko warn karo (Owner only)\n";
        $help_text .= "/ban @username - User ko ban karo (Owner only)\n\n";
        $help_text .= "⚙️ <b>Current Settings</b>\n";
        $help_text .= "Auto Delete: $delete_hours hours\n";
        $help_text .= "Flood Control: 3 messages/5 seconds\n";
        $help_text .= "Warn Limit: 3 warnings = ban";
        
        tg("sendMessage", [
            "chat_id" => $chat_id,
            "text" => $help_text,
            "parse_mode" => "HTML"
        ]); 
        exit;
    }

    // Rules command
    if($message_text == "/rules"){
        $rules_text = "📜 <b>Group Rules</b>\n\n";
        $rules_text .= "1️⃣ Respect karo sabko\n";
        $rules_text .= "2️⃣ Gaali mat do\n";
        $rules_text .= "3️⃣ Ads / Links ❌\n";
        $rules_text .= "4️⃣ Sirf movie request allowed\n";
        $rules_text .= "5️⃣ Admin decision final ✅\n\n";
        $rules_text .= "⚠️ Rule break = Warn/Mute/Ban";
        
        tg("sendMessage", [
            "chat_id" => $chat_id,
            "text" => $rules_text,
            "parse_mode" => "HTML"
        ]);
        exit;
    }

    // Auto delete time change command
    if(strpos($message_text, "/setdelete") === 0){
        if($user_id != $OWNER_ID){
            tg("sendMessage", [
                "chat_id" => $chat_id, 
                "text" => "❌ Sirf owner use kar sakta"
            ]);
            exit;
        }

        $parts = explode(" ", $message_text);
        if(isset($parts[1]) && preg_match("/^([0-9]+)h$/", $parts[1], $matches)){
            $new_delete_hours = intval($matches[1]);
            
            // Update meta file
            @file_put_contents($meta_file, json_encode(["hours" => $new_delete_hours]));
            $delete_hours = $new_delete_hours;

            tg("sendMessage", [
                "chat_id" => $chat_id,
                "text" => "✅ Auto delete set to $new_delete_hours hours"
            ]);
            
            log_admin("⏱ Delete time set: $new_delete_hours Hrs");
        } else {
            tg("sendMessage", [
                "chat_id" => $chat_id,
                "text" => "Usage: /setdelete 24h\nExample: /setdelete 12h"
            ]);
        }
        exit;
    }

    // Warn command
    if(strpos($message_text, "/warn") === 0){
        if($user_id != $OWNER_ID){
            tg("sendMessage", [
                "chat_id" => $chat_id, 
                "text" => "❌ Sirf owner warn kar sakta"
            ]);
            exit;
        }

        $parts = explode(" ", $message_text);
        if(!isset($parts[1])){
            tg("sendMessage", [
                "chat_id" => $chat_id,
                "text" => "Usage: /warn @username\nExample: /warn @john"
            ]);
            exit;
        }

        $target_username = trim($parts[1]);
        
        // Username format check karo
        if (strpos($target_username, '@') !== 0) {
            $target_username = '@' . $target_username;
        }

        // Warn count initialize karo
        if(!isset($warns[$target_username])) {
            $warns[$target_username] = 0;
        }
        
        $warns[$target_username]++;
        @file_put_contents($warn_file, json_encode($warns));

        $current_warns = $warns[$target_username];
        
        tg("sendMessage", [
            "chat_id" => $chat_id,
            "text" => "⚠️ Warning di gayi $target_username ko ($current_warns/3)"
        ]);

        log_admin("⚠️ Warned: $target_username ($current_warns/3)");

        // Agar 3 warnings ho gaye to ban karo
        if($current_warns >= 3){
            tg("sendMessage", [
                "chat_id" => $chat_id,
                "text" => "🚫 $target_username ko 3 warnings mil chuke hain (Ban manually karo)"
            ]);

            log_admin("🚫 Max warnings reached: $target_username - Manual ban required");
            
            // Warnings reset karo
            unset($warns[$target_username]);
            @file_put_contents($warn_file, json_encode($warns));
        }
        exit;
    }

    // Manual ban command
    if(strpos($message_text, "/ban") === 0){
        if($user_id != $OWNER_ID){
            tg("sendMessage", [
                "chat_id" => $chat_id, 
                "text" => "❌ Sirf owner ban kar sakta"
            ]);
            exit;
        }

        $parts = explode(" ", $message_text);
        if(!isset($parts[1])){
            tg("sendMessage", [
                "chat_id" => $chat_id,
                "text" => "Usage: /ban @username\nExample: /ban @john"
            ]);
            exit;
        }

        $target_username = trim($parts[1]);
        
        // Username format check karo
        if (strpos($target_username, '@') !== 0) {
            $target_username = '@' . $target_username;
        }

        tg("sendMessage", [
            "chat_id" => $chat_id,
            "text" => "🚫 $target_username ban ho gaya (Manually ban karo agar needed)"
        ]);
        
        log_admin("⛔ Manual ban command: $target_username");

        // Warnings remove karo
        if(isset($warns[$target_username])) {
            unset($warns[$target_username]);
            @file_put_contents($warn_file, json_encode($warns));
        }
        exit;
    }

    // Group me normal chat ignore karo (sirf commands process karo)
    if($chat_id == $GROUP_ID && !empty($message_text) && strpos($message_text, "/") !== 0){
        // Normal messages ko process nahi karna
        exit;
    }
}

// ========== Inline Button Handler ==========
if(isset($update["callback_query"])){
    $callback_query = $update["callback_query"];
    $callback_data = $callback_query["data"];
    $callback_chat_id = $callback_query["message"]["chat"]["id"];
    $callback_user_id = $callback_query["from"]["id"];
    $callback_message_id = $callback_query["message"]["message_id"];

    // Rules button
    if($callback_data == "show_rules"){
        $rules_text = "📜 <b>Group Rules</b>\n\n";
        $rules_text .= "1️⃣ <b>Respect Everyone</b> - Sab members ko respect do\n";
        $rules_text .= "2️⃣ <b>No Abuse</b> - Gaali galoch strictly prohibited\n";
        $rules_text .= "3️⃣ <b>No Ads/Links</b> - Bina permission kisi bhi tarah ke ads ya links share mat karo\n";
        $rules_text .= "4️⃣ <b>Movie Requests Only</b> - Sirf movie related discussions allowed\n";
        $rules_text .= "5️⃣ <b>Admin Decision Final</b> - Admin ka decision final hai\n\n";
        $rules_text .= "⚠️ <b>Penalties:</b>\n";
        $rules_text .= "• Bad words = 2 min mute\n";
        $rules_text .= "• Spam = 1 min mute\n";
        $rules_text .= "• 3 Warnings = Permanent ban";
        
        tg("sendMessage", [
            "chat_id" => $callback_chat_id,
            "text" => $rules_text,
            "parse_mode" => "HTML"
        ]);
    }

    // Request guide button
    if($callback_data == "req_guide"){
        $guide_text = "🎬 <b>Movie Request Guide</b>\n\n";
        $guide_text .= "📝 <b>Format Follow karo:</b>\n";
        $guide_text .= "<code>Movie Name (Year) [Quality]</code>\n\n";
        $guide_text .= "🔹 <b>Examples:</b>\n";
        $guide_text .= "• <code>Inception (2010) [1080p]</code>\n";
        $guide_text .= "• <code>3 Idiots (2009) [720p]</code>\n";
        $guide_text .= "• <code>Avengers Endgame (2019) [4K]</code>\n\n";
        $guide_text .= "🎯 <b>Quality Options:</b> 480p, 720p, 1080p, 4K\n";
        $guide_text .= "⏰ <b>Response Time:</b> 1-2 hours\n";
        $guide_text .= "✅ <b>Note:</b> Sirf Bollywood & Hollywood movies";
        
        tg("sendMessage", [
            "chat_id" => $callback_chat_id,
            "text" => $guide_text,
            "parse_mode" => "HTML"
        ]);
    }
    
    // Callback query answer bhejo (to remove loading state)
    tg("answerCallbackQuery", [
        "callback_query_id" => $callback_query["id"],
        "text" => "Processing..."
    ]);
}

// Agar yahan tak pahuche ho to successful execution
http_response_code(200);
echo "OK";

// Final cleanup - messages file save karo
@file_put_contents($delete_file, json_encode($messages));

?>
