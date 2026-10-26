// The curated highlight.js grammar set shared by the rich composer
// (CodeBlockLowlight, via lowlight-instance.js) and the rendered-post
// highlighter (code-highlight.js, via highlight.js core). One list so a code
// block is tokenised identically whether it is being written or read.
//
// The full highlight.js catalogue is ~190 languages; keep this tight and add
// only when a real need turns up.

import bash from 'highlight.js/lib/languages/bash';
import css from 'highlight.js/lib/languages/css';
import diff from 'highlight.js/lib/languages/diff';
import javascript from 'highlight.js/lib/languages/javascript';
import json from 'highlight.js/lib/languages/json';
import markdown from 'highlight.js/lib/languages/markdown';
import php from 'highlight.js/lib/languages/php';
import phpTemplate from 'highlight.js/lib/languages/php-template';
import plaintext from 'highlight.js/lib/languages/plaintext';
import sql from 'highlight.js/lib/languages/sql';
import typescript from 'highlight.js/lib/languages/typescript';
import xml from 'highlight.js/lib/languages/xml';
import yaml from 'highlight.js/lib/languages/yaml';

/** @type {Record<string, unknown>} name -> highlight.js language definition */
export const LANGUAGE_DEFINITIONS = {
    bash,
    css,
    diff,
    javascript,
    json,
    markdown,
    php,
    'php-template': phpTemplate,
    plaintext,
    sql,
    typescript,
    xml,
    yaml,
};

/** @type {Record<string, string[]>} canonical name -> fence aliases people type */
export const LANGUAGE_ALIASES = {
    bash: ['sh', 'shell', 'zsh'],
    javascript: ['js', 'node'],
    typescript: ['ts'],
    xml: ['html', 'xhtml', 'vue', 'blade'],
    markdown: ['md'],
    plaintext: ['text', 'txt'],
    diff: ['patch'],
    yaml: ['yml'],
};

/**
 * Languages offered in the composer's code-block picker, in menu order.
 * `value` matches a key in LANGUAGE_DEFINITIONS; empty value means "no language".
 */
export const CODE_LANGUAGES = [
    { value: '', label: 'Plain text' },
    { value: 'php', label: 'PHP' },
    { value: 'javascript', label: 'JavaScript' },
    { value: 'typescript', label: 'TypeScript' },
    { value: 'xml', label: 'HTML / XML' },
    { value: 'css', label: 'CSS' },
    { value: 'bash', label: 'Shell' },
    { value: 'json', label: 'JSON' },
    { value: 'sql', label: 'SQL' },
    { value: 'yaml', label: 'YAML' },
    { value: 'markdown', label: 'Markdown' },
    { value: 'diff', label: 'Diff' },
];
