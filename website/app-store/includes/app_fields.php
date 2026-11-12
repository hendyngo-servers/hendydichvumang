<?php $v=$app??[]; ?>
<div class="store-form-grid">
<div><label>Tên ứng dụng / trò chơi *</label><input name="name" maxlength="150" required value="<?=e($v['name']??'')?>"></div>
<div><label>Nhà phát triển *</label><input name="dev" maxlength="150" required value="<?=e($v['dev']??'')?>"></div>
<div><label>Danh mục</label><select name="category"><option value="app" <?=($v['category']??'app')==='app'?'selected':''?>>Ứng dụng</option><option value="game" <?=($v['category']??'')==='game'?'selected':''?>>Trò chơi</option></select></div>
<div><label>Đánh giá (0–5)</label><input type="number" step="0.1" min="0" max="5" name="rating" value="<?=e((string)($v['rating']??'5.0'))?>"></div>
<div><label>Giá hiển thị</label><input name="price" maxlength="50" value="<?=e($v['price']??'Miễn phí')?>"></div>
<div><label>Phiên bản</label><input name="version" maxlength="20" value="<?=e($v['version']??'1.0.0')?>"></div>
<div><label>Dung lượng</label><input name="size" maxlength="20" value="<?=e($v['size']??'')?>"></div>
<div><label>Trạng thái</label><select name="status"><option value="published" <?=($v['status']??'published')==='published'?'selected':''?>>Công khai</option><option value="draft" <?=($v['status']??'')==='draft'?'selected':''?>>Bản nháp</option></select></div>
</div>
<label>Link icon (HTTPS) *</label><input type="url" name="icon" required value="<?=e($v['icon']??'')?>" placeholder="https://example.com/icon.png">
<label>Mô tả ngắn *</label><textarea name="short_description" rows="3" maxlength="2000" required><?=e($v['short_description']??'')?></textarea>
<label>Mô tả chi tiết</label><textarea name="long_description" rows="7"><?=e($v['long_description']??'')?></textarea>
<label>Link tải xuống (HTTPS)</label><input type="url" name="download_link" value="<?=e($v['download_link']??'')?>" placeholder="https://...">
<label>Ảnh chụp màn hình (JPG, PNG, WEBP, GIF; 5 MB/ảnh, tối đa 8 ảnh/lần)</label><input type="file" name="screenshots[]" accept="image/jpeg,image/png,image/webp,image/gif" multiple>
