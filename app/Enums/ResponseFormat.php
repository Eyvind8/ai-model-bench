<?php

namespace App\Enums;

enum ResponseFormat: string
{
    case Text = 'text';
    case JsonObject = 'json_object';
    case JsonSchema = 'json_schema';
}
