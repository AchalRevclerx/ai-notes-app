<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light dark">
    <title>Margin | AI notes</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Geist:wght@400;500;600&family=Geist+Mono:wght@400;500&display=swap">
    <link rel="stylesheet" href="/assets/app.css?v={{ filemtime(public_path('assets/app.css')) }}">
    <script type="module" src="/assets/app.js?v={{ filemtime(public_path('assets/app.js')) }}"></script>
</head>
<body>
    <p id="boot" class="boot" aria-live="polite">Loading…</p>

    {{-- Sign in / register --}}
    <main id="auth" class="auth" hidden>
        <section class="auth-intro">
            <p class="wordmark">Margin</p>
            <h1>Notes you can find by what they mean.</h1>
            <p class="lede">Search your notes in plain words and get a short AI summary of any note.</p>
            <dl class="auth-points">
                <div>
                    <dt>Semantic search</dt>
                    <dd>"Saving for retirement" finds your SIP plan, even without those words in it.</dd>
                </div>
                <div>
                    <dt>One-click summaries</dt>
                    <dd>Long meeting notes become three sentences you can scan.</dd>
                </div>
                <div>
                    <dt>Private by default</dt>
                    <dd>Every note belongs to your account. Nobody else can read or search it.</dd>
                </div>
            </dl>
        </section>

        <section class="auth-panel" aria-labelledby="auth-heading">
            <div class="segmented" role="tablist" aria-label="Account">
                <button type="button" role="tab" id="tab-login" aria-selected="true" data-mode="login">Log in</button>
                <button type="button" role="tab" id="tab-register" aria-selected="false" data-mode="register">Create account</button>
            </div>
            <h2 id="auth-heading" class="sr-only">Log in</h2>

            <form id="auth-form" class="stack" novalidate>
                <div class="field" data-for="name" hidden>
                    <label for="auth-name">Name</label>
                    <input id="auth-name" name="name" autocomplete="name" maxlength="100">
                    <p class="field-error" data-error="name"></p>
                </div>
                <div class="field">
                    <label for="auth-email">Email</label>
                    <input id="auth-email" name="email" type="email" autocomplete="email" required>
                    <p class="field-error" data-error="email"></p>
                </div>
                <div class="field">
                    <label for="auth-password">Password</label>
                    <input id="auth-password" name="password" type="password" autocomplete="current-password" required>
                    <p class="field-help" data-help="password" hidden>At least 8 characters, with letters and numbers.</p>
                    <p class="field-error" data-error="password"></p>
                </div>
                <p class="form-error" id="auth-error" role="alert"></p>
                <button type="submit" class="btn btn-primary btn-block" id="auth-submit">Log in</button>
            </form>
            <p class="hint">Demo account after <code>php artisan db:seed</code>: <code>demo@example.com</code> / <code>password123</code></p>
        </section>
    </main>

    {{-- Workspace --}}
    <div id="workspace" class="workspace" data-view="list" hidden>
        <header class="topbar">
            <span class="wordmark">Margin</span>
            <form id="search-form" class="search" role="search">
                <label for="search-input" class="sr-only">Search notes by meaning</label>
                <input id="search-input" type="search" placeholder="Search notes by meaning" autocomplete="off" maxlength="500">
                <kbd aria-hidden="true">/</kbd>
            </form>
            <div class="account">
                <span id="user-name" class="user-name"></span>
                <button type="button" id="logout" class="btn btn-ghost">Log out</button>
            </div>
        </header>

        <div class="panes">
            <aside class="list-pane" aria-labelledby="list-title">
                <div class="list-head">
                    <h2 id="list-title">All notes</h2>
                    <button type="button" id="new-note" class="btn btn-primary">New note</button>
                </div>
                <div id="list-meta" class="list-meta"></div>
                <ul id="note-list" class="note-list" aria-busy="false"></ul>
                <nav id="pager" class="pager" aria-label="Pagination" hidden>
                    <button type="button" id="prev-page" class="btn btn-ghost">Previous</button>
                    <span id="page-info" class="mono"></span>
                    <button type="button" id="next-page" class="btn btn-ghost">Next</button>
                </nav>
            </aside>

            <section class="editor-pane" aria-label="Note editor">
                <div id="editor-empty" class="editor-empty">
                    <h2>Pick a note, or start a new one.</h2>
                    <p>Press <kbd>/</kbd> to search, <kbd>Ctrl</kbd> + <kbd>S</kbd> to save.</p>
                </div>

                <form id="editor" class="editor" novalidate hidden>
                    <div class="editor-bar">
                        <button type="button" id="back" class="btn btn-ghost back">All notes</button>
                        <span id="save-state" class="save-state" aria-live="polite"></span>
                        <div class="editor-actions">
                            <button type="button" id="delete" class="btn btn-quiet-danger">Delete</button>
                            <button type="button" id="summarize" class="btn btn-secondary">Summarize</button>
                            <button type="submit" id="save" class="btn btn-primary">Save</button>
                        </div>
                    </div>

                    <div class="editor-body">
                        <label for="note-title" class="sr-only">Title</label>
                        <textarea id="note-title" class="title-input autosize" name="title" rows="1" placeholder="Untitled" maxlength="255"></textarea>
                        <p class="field-error" data-error="title"></p>

                        <section id="summary" class="summary" aria-labelledby="summary-heading" hidden>
                            <div class="summary-head">
                                <h3 id="summary-heading">AI summary</h3>
                                <span id="summary-meta" class="summary-meta mono"></span>
                                <button type="button" id="regenerate" class="btn btn-link">Regenerate</button>
                            </div>
                            <p id="summary-text"></p>
                        </section>
                        <p id="summary-error" class="form-error" role="alert"></p>

                        <label for="note-content" class="sr-only">Content</label>
                        <textarea id="note-content" class="content-input autosize" name="content" placeholder="Start writing…" maxlength="20000"></textarea>
                        <p class="field-error" data-error="content"></p>
                    </div>

                    <footer class="editor-foot mono">
                        <span id="index-state"></span>
                        <span id="char-count"></span>
                    </footer>
                </form>
            </section>
        </div>
    </div>

    <div id="toast" class="toast" role="status" aria-live="polite"></div>
</body>
</html>
