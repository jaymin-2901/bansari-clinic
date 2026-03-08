<?php
/**
 * Uploads Directory - Security Index
 * This file prevents direct directory access
 */
http_response_code(403);
echo "Access Denied";
exit;

