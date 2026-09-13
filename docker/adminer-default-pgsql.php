<?php

/**
 * Pre-select PostgreSQL on the Adminer login form.
 * The official image defaults to MySQL, which yields "db: Connection refused"
 * against our Postgres service.
 */
class AdminerDefaultPgsql
{
    public function loginForm(): void
    {
        $nonce = function_exists('nonce') ? nonce() : '';

        echo <<<HTML
<script{$nonce}>
document.addEventListener('DOMContentLoaded', function () {
    var driver = document.querySelector('[name="auth[driver]"]');
    if (!driver) {
        return;
    }
    driver.value = 'pgsql';
    if (typeof driver.onchange === 'function') {
        driver.onchange();
    }
});
</script>
HTML;
    }
}

return new AdminerDefaultPgsql();
