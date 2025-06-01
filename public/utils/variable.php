<?php

function str_to_bool(string $val): bool {
  return strtolower($val) === 'true' || $val === '1';
}

function bool_to_str(bool $val): string {
  return $val ? 'true' : 'false';
}
