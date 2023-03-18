<?php
// 单点登录
function http_request($url, $is_post = False, $post_data = null)
{
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_VERBOSE, 1);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    if ($is_post) {
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post_data));
    }
    $resp = curl_exec($ch);
    curl_close($ch);
    return $resp;
}

require_once("./include/db_info.inc.php");
require_once("./include/my_func.inc.php");

if (isset($_GET['code'])) {
    $code = $_GET['code'];
    $GURL = "https://class.kids123code.com/local/oauth/token.php";
    $vars = array(
        'client_id' => 'oj.kids123code.com',
        'client_secret' => '0b3f321adef0647ca56cc098f7159062ddc0f099e80fa23c',
        'grant_type' => 'authorization_code',
        'redirect_uri' => 'https://oj.kids123code.com/login_class.php',
        'code' => $code
    );
    $ret = http_request($GURL, True, $vars);
    $data = json_decode($ret, true);
    $access_token = $data['access_token'];
    # userinfo
    $req = array(
        "access_token" => $access_token
    );
    $ret = http_request("https://class.kids123code.com/local/oauth/user_info.php", True, $req);
    $data = json_decode($ret, true);
    if (isset($data['username'])) {
        // register this user and login it
        $uname = $data['username'];
        $first_name = empty($data['firstname']) ? '' : $data['firstname'];
        $last_name = empty($data['lastname']) ? '无名氏' : $data['lastname'];
        $nick = $last_name . $first_name;
        $password = '';
        $email = "";
        $school = "";
        // check first
        $sql = "SELECT * FROM `users` where `user_id`=?";
        $res = pdo_query($sql, $uname);
        $row_num = count($res);
        if ($row_num == 0) {
            $sql = "INSERT INTO `users`("
                . "`user_id`,`email`,`ip`,`accesstime`,`password`,`reg_time`,`nick`,`school`)"
                . "VALUES(?,?,?,NOW(),?,NOW(),?,?)";
            // reg it
            $ip = ($_SERVER['REMOTE_ADDR']);
            if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
                $REMOTE_ADDR = $_SERVER['HTTP_X_FORWARDED_FOR'];
                $tmp_ip = explode(',', $REMOTE_ADDR);
                $ip = (htmlentities($tmp_ip[0], ENT_QUOTES, "UTF-8"));
            }
            pdo_query($sql, $uname, $email, $ip, $password, $nick, $school);
        } else {
            if ($res[0]['defunct'] == 'Y') {
                die('你已经被封禁');
            }
        }

        // login it
        $_SESSION[$OJ_NAME . '_' . 'user_id'] = $uname;

        // privileges
        $sql = "SELECT * FROM `privilege` WHERE `user_id`=?";
        $result = pdo_query($sql, $uname);

        foreach ($result as $row) {
            if (isset($row['valuestr']))
                $_SESSION[$OJ_NAME . '_' . $row['rightstr']] = $row['valuestr'];
            else
                $_SESSION[$OJ_NAME . '_' . $row['rightstr']] = true;
        }
        if (isset($_SESSION[$OJ_NAME . '_vip'])) {  // VIP mark can access all [VIP] marked contest
            $sql = "select contest_id from contest where title like '%[VIP]%'";
            $result = pdo_query($sql);
            foreach ($result as $row) {
                $_SESSION[$OJ_NAME . '_c' . $row['contest_id']] = true;
            }
        };
        // redirect it
        header("Location: ./");
    } else {
        echo "Login Expire!";
    }
} else {
    header("location: https://class.kids123code.com/local/oauth/login.php?response_type=code&client_id=oj.kids123code.com&redirect_uri=https://oj.kids123code.com/login_class.php&scope=user_info");
}
