import DOMPurify from 'dompurify';

const RICH_TEXT_CONFIG: DOMPurify.Config = {
    ALLOWED_TAGS: [
        'p', 'br', 'strong', 'em', 'u', 's', 'ul', 'ol', 'li',
        'h1', 'h2', 'h3', 'h4', 'blockquote', 'a', 'code', 'pre',
    ],
    ALLOWED_ATTR: ['href', 'title', 'target', 'rel'],
    ALLOWED_URI_REGEXP: /^(?:(?:https?|mailto|tel):|[^a-z]|[a-z+.\-]+(?:[^a-z+.\-:]|$))/i,
};

export function sanitizeRichText(dirty: string | null | undefined): string {
    if (!dirty) return '';
    return DOMPurify.sanitize(dirty, RICH_TEXT_CONFIG) as string;
}
