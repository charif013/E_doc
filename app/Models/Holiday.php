<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Holiday extends Model
{
    use HasFactory;

    // 🌟 เพิ่มบรรทัดนี้เพื่ออนุญาตให้ 3 คอลัมน์นี้บันทึกข้อมูลแบบอัตโนมัติได้
    protected $fillable = [
        'holiday_date', 
        'name', 
        'source'
    ];
}