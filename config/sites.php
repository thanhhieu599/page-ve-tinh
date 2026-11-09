<?php

return [

    /*
    | Ép mọi URL công khai về https. Tắt ở local và trong job kiểm tra
    | trình duyệt của CI (chạy http thuần), bật trên staging/production.
    */
    'force_https' => (bool) env('FORCE_HTTPS', true),

];
