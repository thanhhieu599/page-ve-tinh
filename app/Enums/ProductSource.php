<?php

namespace App\Enums;

/** Nguồn dữ liệu sản phẩm. */
enum ProductSource: string
{
    case Manual = 'manual';
    case Csv = 'csv';
    case Api = 'api';
}
