// Đo Core Web Vitals (Lighthouse, giả lập điện thoại + mạng chậm) cho từng loại trang.
// Vượt ngưỡng → thoát mã 1 để CI đỏ. Chạy trên ứng dụng ở APP_ORIGIN với dữ liệu DemoSiteSeeder.
//
// Ngưỡng (theo chuẩn "Tốt" của Google):
//   LCP < 2,5 giây · CLS < 0,1 · TBT < 200 ms (thay cho INP, vì INP cần người dùng thật tương tác).
import { request } from 'node:http';
import lighthouse from 'lighthouse';
import { chromium } from 'playwright';

const origin = new URL(process.env.APP_ORIGIN ?? 'http://127.0.0.1:8000');
const domain = 'vpptrangthietbi.com.vn';
const port = 9333;

const pages = [
    '/',
    '/kien-thuc/cach-chon-giay-in-a4',
    '/danh-muc/giay-van-phong',
    '/san-pham/giay-double-a-a4-80gsm',
    '/tac-gia/bien-tap-dt',
    '/lien-he',
    '/yeu-cau-bao-gia',
];

const limits = {
    'largest-contentful-paint': { max: 2500, label: 'LCP', unit: 'ms' },
    'cumulative-layout-shift': { max: 0.1, label: 'CLS', unit: '' },
    'total-blocking-time': { max: 200, label: 'TBT', unit: 'ms' },
};

/** Gọi trang một lần (đúng tên miền của site) để cache toàn trang có sẵn trước khi đo. */
function warm(path) {
    return new Promise((resolve, reject) => {
        const req = request({ host: origin.hostname, port: origin.port || 80, path, headers: { Host: `${domain}:${origin.port || 80}` } }, (res) => {
            res.resume();
            res.on('end', () => (res.statusCode === 200 ? resolve() : reject(new Error(`${path}: HTTP ${res.statusCode}`))));
        });
        req.on('error', reject);
        req.end();
    });
}

const browser = await chromium.launchServer({
    args: [`--remote-debugging-port=${port}`, `--host-resolver-rules=MAP ${domain} ${origin.hostname}`],
});

let failed = false;

for (const path of pages) {
    const url = `http://${domain}:${origin.port || 80}${path}`;
    // Lượt đầu làm nóng cache toàn trang, lượt đo phản ánh trang như khách thường gặp.
    await warm(path);

    const result = await lighthouse(url, { port, output: 'json', logLevel: 'error', onlyCategories: ['performance'] });
    const audits = result.lhr.audits;
    const parts = [];
    let pageFailed = false;

    for (const [id, { max, label, unit }] of Object.entries(limits)) {
        const value = audits[id]?.numericValue;
        if (typeof value !== 'number') {
            console.error(`FAIL ${path}: không đo được ${label}`);
            pageFailed = true;
            continue;
        }
        const shown = unit === 'ms' ? `${Math.round(value)}ms` : value.toFixed(3);
        parts.push(`${label} ${shown}`);
        if (value >= max) {
            console.error(`FAIL ${path}: ${label} ${shown} vượt ngưỡng ${unit === 'ms' ? max + 'ms' : max}`);
            pageFailed = true;
        }
    }

    failed ||= pageFailed;
    console.log(`${pageFailed ? '    ' : 'OK  '} ${path}: ${parts.join(' · ')}`);
}

await browser.close();
process.exit(failed ? 1 : 0);
