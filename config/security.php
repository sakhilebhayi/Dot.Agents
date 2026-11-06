<?php

return [
    /*
    | Strict-Transport-Security max-age in seconds, on HTTPS responses only. 0 turns it off. Subdomains are left out on purpose:
    | the other Dot platforms sit on neighbouring subdomains and set their own.
    */
    'hsts_seconds' => (int) env('SECURITY_HSTS_SECONDS', 31536000),

    /*
    | The Content-Security-Policy is enforced. SECURITY_CSP_REPORT_ONLY=true is the way back without a deploy: the browser then reports
    | what it would have blocked and blocks nothing.
    */
    'csp_report_only' => (bool) env('SECURITY_CSP_REPORT_ONLY', false),
];
