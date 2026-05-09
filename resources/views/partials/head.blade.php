<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<meta name="description" content="MyCMS is a modern CRM platform that helps you manage customers, streamline workflows, and grow your business efficiently." />
<meta name="keywords" content="CRM, customer relationship management, sales, leads, business management, MyCMS" />
<meta name="author" content="MyCMS" />
<meta name="robots" content="index, follow, max-image-preview:large, max-snippet:-1, max-video-preview:-1" />
<meta name="googlebot" content="index, follow, max-image-preview:large, max-snippet:-1, max-video-preview:-1" />
<meta name="bingbot" content="index, follow, max-image-preview:large, max-snippet:-1, max-video-preview:-1" />
<meta name="referrer" content="strict-origin-when-cross-origin" />
<meta name="format-detection" content="telephone=no, address=no, email=no" />
<meta name="application-name" content="MyCMS" />
<meta name="apple-mobile-web-app-title" content="MyCMS" />
<meta name="theme-color" content="#ffffff" />
<meta name="color-scheme" content="light dark" />

<title>
    {{ filled($title ?? null) ? $title.' - MyCMS' : 'MyCMS' }}
</title>

<link rel="canonical" href="{{ url()->current() }}" />
<link rel="icon" href="/favicon.png" sizes="any">
<link rel="icon" href="/favicon.png" type="image/png">
<link rel="apple-touch-icon" href="/favicon.png">

@fonts

@vite(['resources/css/app.css', 'resources/js/app.js'])
@fluxAppearance
