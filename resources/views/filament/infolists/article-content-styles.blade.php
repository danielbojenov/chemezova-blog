@once
    <style>
        /* Self-contained styling for the article content preview. The admin panel does
           not ship the Tailwind Typography plugin, so rich-text HTML is styled here
           directly rather than relying on `prose`. Shared by the content and TLDR
           entries; the surrounding once-directive keeps it to one copy per page. */
        .article-content-preview {
            --acp-border: rgb(228 228 231);          /* zinc-200 */
            --acp-muted: rgb(113 113 122);           /* zinc-500 */
            --acp-accent: rgb(217 119 6);            /* amber-600 (panel primary) */
            font-size: 0.95rem;
            line-height: 1.7;
        }
        .dark .article-content-preview {
            --acp-border: rgb(255 255 255 / 0.1);
            --acp-muted: rgb(161 161 170);           /* zinc-400 */
            --acp-accent: rgb(251 191 36);           /* amber-400 */
        }

        /* Padding between blocks + a slight separator between them. */
        .article-content-preview .acp-block {
            padding: 1.5rem 0;
        }
        .article-content-preview .acp-block + .acp-block {
            border-top: 1px solid var(--acp-border);
        }
        .article-content-preview .acp-block:first-child {
            padding-top: 0;
        }

        /* Section headings are their own block; they lead the block that follows,
           so the usual inter-block gap and separator are dropped between them. */
        .article-content-preview .acp-heading {
            font-size: 1.35rem;
            font-weight: 700;
            line-height: 1.3;
            padding-bottom: 0.25rem;
        }
        .article-content-preview .acp-heading + .acp-block {
            border-top: 0;
            padding-top: 0;
        }

        /* Rich text typography (restores element styling stripped by Preflight). */
        .acp-richtext :is(h1, h2, h3, h4) {
            font-weight: 600;
            line-height: 1.3;
            margin: 1.25rem 0 0.5rem;
        }
        .acp-richtext h1 { font-size: 1.6rem; }
        .acp-richtext h2 { font-size: 1.35rem; }
        .acp-richtext h3 { font-size: 1.15rem; }
        .acp-richtext :first-child { margin-top: 0; }
        .acp-richtext p { margin: 0.75rem 0; }
        .acp-richtext :is(ul, ol) { margin: 0.75rem 0; padding-left: 1.5rem; }
        .acp-richtext ul { list-style: disc; }
        .acp-richtext ol { list-style: decimal; }
        .acp-richtext li { margin: 0.25rem 0; }
        .acp-richtext a { color: var(--acp-accent); text-decoration: underline; }
        .acp-richtext blockquote {
            margin: 1rem 0;
            padding-left: 1rem;
            border-left: 3px solid var(--acp-border);
            color: var(--acp-muted);
        }
        .acp-richtext :is(strong, b) { font-weight: 600; }

        /* Images: -mobile variant, centered, capped, with a muted caption. */
        .acp-figure { text-align: center; }
        .acp-figure img {
            max-width: 100%;
            height: auto;
            border-radius: 0.5rem;
            display: inline-block;
        }
        .acp-figure figcaption {
            margin-top: 0.5rem;
            font-size: 0.85rem;
            font-style: italic;
            color: var(--acp-muted);
        }

        /* FAQ: standout heading + accordion cards. */
        .acp-faq-heading {
            font-size: 1.3rem;
            font-weight: 700;
            padding-bottom: 0.5rem;
            margin-bottom: 1rem;
            border-bottom: 2px solid var(--acp-accent);
        }
        .acp-faq-item {
            border: 1px solid var(--acp-border);
            border-radius: 0.5rem;
            padding: 0.75rem 1rem;
            margin-bottom: 0.75rem;
        }
        .acp-faq-item > summary {
            cursor: pointer;
            font-weight: 600;
            list-style: none;
        }
        .acp-faq-item > summary::-webkit-details-marker { display: none; }
        .acp-faq-item > summary::before {
            content: '▸';
            display: inline-block;
            margin-right: 0.5rem;
            color: var(--acp-accent);
            transition: transform 0.15s ease;
        }
        .acp-faq-item[open] > summary::before { transform: rotate(90deg); }
        .acp-faq-answer { margin-top: 0.5rem; color: var(--acp-muted); }
        .acp-faq-answer p { margin: 0.5rem 0; }
        .acp-faq-answer :first-child { margin-top: 0; }

        /* Product cards: rank badge, image beside a facts list, and buy buttons. */
        .acp-product { position: relative; }
        .acp-product-rank {
            display: inline-block;
            font-size: 0.8rem;
            font-weight: 700;
            line-height: 1;
            padding: 0.3rem 0.55rem;
            margin-bottom: 0.75rem;
            border-radius: 0.375rem;
            color: rgb(255 255 255);
            background: var(--acp-accent);
        }
        .acp-product-body {
            display: flex;
            flex-wrap: wrap;
            gap: 1rem;
            align-items: flex-start;
        }
        .acp-product-image {
            width: 8rem;
            max-width: 100%;
            height: auto;
            border-radius: 0.5rem;
            border: 1px solid var(--acp-border);
        }
        .acp-product-main { flex: 1 1 16rem; min-width: 0; }
        .acp-product-name { font-size: 1.15rem; font-weight: 700; line-height: 1.3; }
        .acp-product-brand { font-size: 0.85rem; color: var(--acp-muted); margin-top: 0.15rem; }
        .acp-product-facts {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem 1.25rem;
            margin: 0.75rem 0;
        }
        .acp-product-facts dt {
            font-size: 0.7rem;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            color: var(--acp-muted);
        }
        .acp-product-facts dd { font-size: 0.9rem; font-weight: 600; }
        .acp-product-ingredients {
            display: flex;
            flex-wrap: wrap;
            gap: 0.35rem;
            margin: 0.5rem 0;
        }
        .acp-product-ingredients span {
            font-size: 0.75rem;
            padding: 0.15rem 0.5rem;
            border-radius: 999px;
            border: 1px solid var(--acp-border);
            color: var(--acp-muted);
        }
        .acp-product-description { margin-top: 0.5rem; }
        .acp-product-links {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
            margin-top: 0.75rem;
        }
        .acp-product-buy {
            display: inline-block;
            font-size: 0.85rem;
            font-weight: 600;
            padding: 0.4rem 0.9rem;
            border-radius: 0.375rem;
            text-decoration: none;
            color: rgb(255 255 255);
            background: var(--acp-accent);
        }
        .acp-product-buy:hover { opacity: 0.9; }

        .acp-empty { color: var(--acp-muted); font-size: 0.9rem; }
    </style>
@endonce
