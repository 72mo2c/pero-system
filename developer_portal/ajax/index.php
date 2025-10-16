<?php
// منع الوصول المباشر لمجلد AJAX
header('HTTP/1.0 403 Forbidden');
exit('Access Denied');
?>