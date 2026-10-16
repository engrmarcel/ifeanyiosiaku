<?php

return [

    // Code-block languages offered to editors (REQ-CMS-6). Keys are the highlighter's language ids.
    'code_languages' => [
        'plaintext' => 'Plain text',
        'html' => 'HTML',
        'css' => 'CSS',
        'javascript' => 'JavaScript',
        'typescript' => 'TypeScript',
        'jsx' => 'JSX',
        'tsx' => 'TSX',
        'php' => 'PHP',
        'python' => 'Python',
        'sql' => 'SQL',
        'json' => 'JSON',
        'bash' => 'Bash / shell',
        'powershell' => 'PowerShell',
        'yaml' => 'YAML',
        'markdown' => 'Markdown',
        'r' => 'R',
        'java' => 'Java',
        'csharp' => 'C#',
        'go' => 'Go',
        'dax' => 'DAX (Power BI)',
    ],

    // Preview links for unpublished drafts expire after this many minutes.
    'preview_ttl_minutes' => 15,

    // Draft revisions kept per page (published and restored snapshots are always kept).
    'max_draft_revisions' => 50,

    // Paths that pages may never use, because the website or API already owns them.
    'reserved_slugs' => [
        'api', 'admin', 'media', 'login', 'register', 'logout', 'account', 'two-factor', 'verify-email',
        'forgot-password', 'reset-password', 'preview', 'healthz', 'sitemap.xml', 'robots.txt',
        'livewire', 'filament', 'css', 'js', 'fonts', 'storage', 'brand', '_next',
        'articles', 'publications', 'page-not-found', 'internal',
    ],

    // Website sections whose items live at /{section}/{slug}. Old item addresses may be redirected.
    'content_sections' => ['projects', 'articles', 'publications'],

];
