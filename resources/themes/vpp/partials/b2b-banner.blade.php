{{-- Khối quảng bá giữa trang (kiểu banner đôi của web bán hàng), nội dung B2B: hợp đồng cung ứng và hàng tiêu hao nhà máy. --}}
<section class="promo-pair" aria-labelledby="cung-ung-dinh-ky">
    <div class="promo-tile promo-tile-dark">
        <p class="promo-kicker">Doanh nghiệp · Nhà máy</p>
        <h2 id="cung-ung-dinh-ky">Cung ứng định kỳ cho nhà máy</h2>
        <p>Đơn hàng tháng/quý, giá ổn định theo hợp đồng, giao theo lịch, một hoá đơn VAT.</p>
        <a class="button rounded-full border-white bg-white px-5 text-sm text-ink hover:bg-white/90 hover:text-ink" href="{{ \App\Http\Controllers\QuoteRequestController::pathFrom('/') }}">Nhận báo giá hợp đồng</a>
    </div>
    <div class="promo-tile promo-tile-red">
        <p class="promo-kicker">Một đầu mối</p>
        <h3>Văn phòng phẩm, vật tư tiêu hao, PPE</h3>
        <p>Gửi một danh sách, nhận một báo giá cho cả văn phòng và xưởng sản xuất.</p>
        <a class="button rounded-full border-white bg-white px-5 text-sm text-brand hover:bg-white/90 hover:text-brand-dark" href="{{ \App\Http\Controllers\QuoteRequestController::pathFrom('/') }}">Gửi danh sách cần mua</a>
    </div>
</section>
