// Markdown <-> ProseMirror bridge for the rich composer.
//
// Keyed to TipTap 3 StarterKit's schema and tuned to match the server's
// renderer: a single "\n" is a hard line break and raw HTML is escaped.
// Plain Markdown only: the old forum's [u]/[color]/[spoiler]/... tags are still
// rendered for posts that have them, but the composer neither reads nor writes
// them (a post that contains one opens in the plain Markdown box instead).

import MarkdownIt from 'markdown-it';
import { MarkdownParser, MarkdownSerializer } from 'prosemirror-markdown';

const md = new MarkdownIt('default', {
    html: false,
    linkify: false,
    typographer: false,
    breaks: true, // single "\n" -> hardbreak, matching MarkdownRenderer
})
    .disable(['table']);

const PARSER_TOKENS = {
    blockquote: { block: 'blockquote' },
    paragraph: { block: 'paragraph' },
    list_item: { block: 'listItem' },
    bullet_list: { block: 'bulletList' },
    ordered_list: {
        block: 'orderedList',
        getAttrs: (tok) => ({ start: Number(tok.attrGet('start')) || 1 }),
    },
    heading: {
        block: 'heading',
        getAttrs: (tok) => ({ level: Number(tok.tag.slice(1)) || 1 }),
    },
    code_block: { block: 'codeBlock', noCloseToken: true },
    fence: {
        block: 'codeBlock',
        getAttrs: (tok) => ({ language: (tok.info || '').trim() || null }),
        noCloseToken: true,
    },
    hr: { node: 'horizontalRule' },
    hardbreak: { node: 'hardBreak' },
    softbreak: { node: 'hardBreak' },
    image: {
        node: 'image',
        getAttrs: (tok) => ({
            src: tok.attrGet('src'),
            title: tok.attrGet('title') || null,
            alt: (tok.children && tok.children[0] && tok.children[0].content) || null,
        }),
    },
    em: { mark: 'italic' },
    strong: { mark: 'bold' },
    s: { mark: 'strike' },
    code_inline: { mark: 'code', noCloseToken: true },
    link: {
        mark: 'link',
        getAttrs: (tok) => ({ href: tok.attrGet('href'), title: tok.attrGet('title') || null }),
    },

};

const SERIALIZER_NODES = {
    paragraph(state, node) {
        state.renderInline(node);
        state.closeBlock(node);
    },
    heading(state, node) {
        state.write(`${'#'.repeat(node.attrs.level || 1)} `);
        state.renderInline(node);
        state.closeBlock(node);
    },
    blockquote(state, node) {
        state.wrapBlock('> ', null, node, () => state.renderContent(node));
    },
    bulletList(state, node) {
        state.renderList(node, '  ', () => '- ');
    },
    orderedList(state, node) {
        const start = node.attrs.start || 1;
        const maxWidth = String(start + node.childCount - 1).length;
        const pad = ' '.repeat(maxWidth + 2);
        state.renderList(node, pad, (index) => {
            const numeral = String(start + index);

            return `${' '.repeat(maxWidth - numeral.length)}${numeral}. `;
        });
    },
    listItem(state, node) {
        state.renderContent(node);
    },
    codeBlock(state, node) {
        state.write(`\`\`\`${node.attrs.language || ''}\n`);
        state.text(node.textContent, false);
        state.ensureNewLine();
        state.write('```');
        state.closeBlock(node);
    },
    horizontalRule(state, node) {
        state.write('---');
        state.closeBlock(node);
    },
    hardBreak(state) {
        state.write('\n');
    },
    image(state, node) {
        const title = node.attrs.title ? ` "${node.attrs.title.replace(/"/g, '\\"')}"` : '';
        state.write(`![${state.esc(node.attrs.alt || '')}](${node.attrs.src || ''}${title})`);
    },
    text(state, node) {
        state.text(node.text);
    },
};

const SERIALIZER_MARKS = {
    bold: { open: '**', close: '**', mixable: true, expelEnclosingWhitespace: true },
    italic: { open: '_', close: '_', mixable: true, expelEnclosingWhitespace: true },
    strike: { open: '~~', close: '~~', mixable: true, expelEnclosingWhitespace: true },
    code: { open: '`', close: '`', escape: false },
    link: {
        open: () => '[',
        close: (state, mark) => {
            const title = mark.attrs.title ? ` "${mark.attrs.title.replace(/"/g, '\\"')}"` : '';

            return `](${mark.attrs.href || ''}${title})`;
        },
    },
};

/**
 * @param {import('@tiptap/pm/model').Schema} schema
 * @param {string} markdown
 * @returns {import('@tiptap/pm/model').Node}
 */
export function parseMarkdown(schema, markdown) {
    const parser = new MarkdownParser(schema, md, PARSER_TOKENS);

    return parser.parse(markdown ?? '') ?? schema.topNodeType.createAndFill();
}

/**
 * @param {import('@tiptap/pm/model').Node} doc
 * @returns {string}
 */
// The serializer holds no state between calls (each serialize() builds its own),
// so build it once instead of on every keystroke.
let markdownSerializer = null;

export function serializeMarkdown(doc) {
    markdownSerializer ??= new MarkdownSerializer(SERIALIZER_NODES, SERIALIZER_MARKS);
    const out = markdownSerializer.serialize(doc, { tightLists: true });

    return out
        .trim();
}
