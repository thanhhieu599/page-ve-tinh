// Kiểm tra bằng trình duyệt thật: tắt JavaScript, nội dung bài vẫn phải hiện đủ.
// Chạy trên ứng dụng đang phục vụ ở APP_ORIGIN (mặc định http://127.0.0.1:8000)
// với dữ liệu từ DemoSiteSeeder. Domain của site được trỏ về APP_ORIGIN.
import { chromium } from 'playwright';

const origin = new URL(process.env.APP_ORIGIN ?? 'http://127.0.0.1:8000');
const domain = 'vpptrangthietbi.com.vn';

const pages = [
    {
        path: '/kien-thuc/cach-chon-giay-in-a4',
        mustContain: [
            'Cách chọn giấy in A4 cho văn phòng nhà máy',
            'Định lượng 70gsm phù hợp in nội bộ, 80gsm cho hồ sơ gửi khách hàng.',
            'Nên chọn giấy 70gsm hay 80gsm?',
            'Ban biên tập D&T',
            'Giấy văn phòng',
        ],
    },
    {
        path: '/danh-muc/giay-van-phong',
        mustContain: ['Giấy văn phòng', 'Cách chọn giấy in A4 cho văn phòng nhà máy'],
    },
    {
        path: '/tac-gia/bien-tap-dt',
        mustContain: ['Ban biên tập D&T', 'Cách chọn giấy in A4 cho văn phòng nhà máy'],
    },
];

const browser = await chromium.launch({
    args: [`--host-resolver-rules=MAP ${domain} ${origin.hostname}`],
});
const context = await browser.newContext({ javaScriptEnabled: false });
const page = await context.newPage();

let failed = false;

for (const { path, mustContain } of pages) {
    const url = `http://${domain}:${origin.port || 80}${path}`;
    const response = await page.goto(url);
    const text = await page.locator('body').innerText();

    if (!response || response.status() !== 200) {
        console.error(`FAIL ${path}: HTTP ${response?.status()}`);
        failed = true;
        continue;
    }

    for (const expected of mustContain) {
        if (!text.includes(expected)) {
            console.error(`FAIL ${path}: thiếu "${expected}" khi tắt JavaScript`);
            failed = true;
        }
    }

    // Ảnh trong bài phải có đủ alt, width, height dù không có JavaScript.
    for (const img of await page.locator('article img').all()) {
        for (const attribute of ['alt', 'width', 'height']) {
            const value = await img.getAttribute(attribute);
            if (value === null || (attribute !== 'alt' && value === '')) {
                console.error(`FAIL ${path}: ảnh thiếu thuộc tính ${attribute}`);
                failed = true;
            }
        }
    }

    if (!failed) {
        console.log(`OK   ${path}`);
    }
}

await browser.close();
process.exit(failed ? 1 : 0);
