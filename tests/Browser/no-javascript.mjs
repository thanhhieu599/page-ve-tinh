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
        ],
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

    if (!failed) {
        console.log(`OK   ${path}`);
    }
}

await browser.close();
process.exit(failed ? 1 : 0);
