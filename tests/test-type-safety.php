<?php

assert(wem_safe_strlen(null) === 0);
assert(wem_safe_strlen('') === 0);
assert(wem_safe_strlen('test') === 4);
assert(wem_safe_strlen(['array']) === 0);

assert(wem_is_empty_string(null) === true);
assert(wem_is_empty_string('') === true);
assert(wem_is_empty_string('test') === false);
