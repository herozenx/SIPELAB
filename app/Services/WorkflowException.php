<?php

namespace App\Services;

use RuntimeException;

/** Pelanggaran aturan alur; pesannya aman ditampilkan ke pengguna. */
class WorkflowException extends RuntimeException {}
