#!/usr/bin/env python3
from __future__ import annotations

import pathlib

path = pathlib.Path(__file__).resolve().parents[1] / 'scripts' / 'audit_repository_production_quality_v2.php'
source = path.read_text(encoding='utf-8')
old = """        if ($variable === '' || preg_match('/(?:^|[|&\\s\\\\])Throwable(?:[|&\\s]|$)/i', $signature) !== 1) continue;
        $blocks[] = ['variable'=>$variable, 'body'=>$body];
"""
new = """        $typeSignature = trim(str_replace($variable, '', $signature));
        $typeNames = preg_split('/[|&\\s]+/', str_replace('\\\\', '', $typeSignature), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        if (!in_array('Throwable', $typeNames, true)) continue;
        $blocks[] = ['variable'=>$variable, 'body'=>$body];
"""
if old not in source:
    raise RuntimeError('Expected Throwable signature parser was not found.')
path.write_text(source.replace(old, new, 1), encoding='utf-8')
print('Replaced escaped Throwable regex with tokenized type-name comparison.')
