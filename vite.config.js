import { readdirSync, existsSync } from 'node:fs';
import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';

// Mỗi theme (thư mục trong resources/themes có theme.css) tự được build: thêm theme không sửa cấu hình.
const themeInputs = readdirSync('resources/themes', { withFileTypes: true })
    .filter((entry) => entry.isDirectory() && existsSync(`resources/themes/${entry.name}/theme.css`))
    .map((entry) => `resources/themes/${entry.name}/theme.css`);

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js', ...themeInputs],
            refresh: true,
        }),
        tailwindcss(),
    ],
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
