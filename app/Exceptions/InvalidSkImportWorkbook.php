<?php

namespace App\Exceptions;

use Illuminate\Contracts\Debug\ShouldntReport;
use RuntimeException;

class InvalidSkImportWorkbook extends RuntimeException implements ShouldntReport {}
