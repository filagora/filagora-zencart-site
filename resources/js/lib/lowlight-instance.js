// Shared lowlight instance for the rich composer's CodeBlockLowlight.
// Grammar set and aliases come from code-languages.js so the rendered-post
// highlighter stays in lockstep.

import { createLowlight } from 'lowlight';
import { LANGUAGE_DEFINITIONS, LANGUAGE_ALIASES } from './code-languages';

export const lowlight = createLowlight();

lowlight.register(LANGUAGE_DEFINITIONS);
lowlight.registerAlias(LANGUAGE_ALIASES);
