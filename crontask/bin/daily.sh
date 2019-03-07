#!/bin/bash
/data/webapps/stock/crontask/bin/admin.sh start wechat_daliy_pool.php
/data/webapps/stock/crontask/bin/admin.sh start wechat_dk_message.php
/data/webapps/stock/crontask/bin/admin.sh stop wechat_t0_message.php
/data/webapps/stock/crontask/bin/admin.sh start wechat_t0_message.php
