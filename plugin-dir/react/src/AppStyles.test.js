import fs from 'fs';
import path from 'path';

const stylesheet = fs.readFileSync(path.join(__dirname, 'App.scss'), 'utf8');

describe('admin stylesheet policy', () => {
    it('covers the WordPress admin page background containers', () => {
        [
            'body[class*="page_wpcf7_vk"] #wpwrap',
            'body[class*="page_wpcf7_vk"] #wpcontent',
            'body[class*="page_wpcf7_vk"] #wpbody',
            'body[class*="page_wpcf7_vk"] #wpbody-content',
        ].forEach((selector) => {
            expect(stylesheet).toContain(selector);
        });
    });

    it('hides unrelated notices without hiding plugin-owned notices', () => {
        [
            '> .notice:not(.cf7vk-notice)',
            '> .updated:not(.cf7vk-notice)',
            '> .error:not(.cf7vk-notice)',
            '> .update-nag:not(.cf7vk-notice)',
        ].forEach((selector) => {
            expect(stylesheet).toContain(selector);
        });
    });

    it('scopes WordPress admin menu arrow styling to the plugin page', () => {
        expect(stylesheet).toContain(
            'body[class*="page_wpcf7_vk"] ul#adminmenu a.wp-has-current-submenu:after'
        );
        expect(stylesheet).not.toMatch(/(^|\n)\s*ul#adminmenu\b/);
    });
});
