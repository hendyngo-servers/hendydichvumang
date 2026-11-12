<?php $v=$service??[]; ?>
<label>Tên gói dịch vụ *</label><input name="name" maxlength="180" required value="<?=e($v['name']??'')?>">
<div class="store-form-grid">
<div><label>Danh mục</label><input name="category" maxlength="100" value="<?=e($v['category']??'Dịch vụ mạng')?>"></div>
<div><label>Giá hiển thị</label><input name="price" maxlength="100" value="<?=e($v['price']??'Liên hệ')?>"></div>
<div><label>Thời hạn</label><input name="duration" maxlength="100" value="<?=e($v['duration']??'')?>"></div>
<div><label>Băng thông / tốc độ</label><input name="bandwidth" maxlength="100" value="<?=e($v['bandwidth']??'')?>"></div>
<div><label>Thứ tự hiển thị</label><input type="number" name="sort_order" min="0" max="999999" value="<?=e((string)($v['sort_order']??'0'))?>"></div>
<div><label>Trạng thái</label><select name="status"><option value="published" <?=($v['status']??'published')==='published'?'selected':''?>>Công khai</option><option value="draft" <?=($v['status']??'')==='draft'?'selected':''?>>Bản nháp</option></select></div>
</div>
<label>Ảnh đại diện (URL HTTPS)</label><input type="url" name="image_url" value="<?=e($v['image_url']??'')?>" placeholder="https://...">
<label>Mô tả *</label><textarea name="description" rows="6" required><?=e($v['description']??'')?></textarea>
<label>Tính năng / quyền lợi (mỗi dòng một ý)</label><textarea name="features" rows="6"><?=e($v['features']??'')?></textarea>
<label>Đường dẫn đăng ký / đặt dịch vụ (URL HTTPS)</label><input type="url" name="order_url" value="<?=e($v['order_url']??'')?>" placeholder="https://...">
