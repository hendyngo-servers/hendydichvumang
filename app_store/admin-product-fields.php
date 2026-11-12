<?php $v = $product ?? []; ?>
<div class="store-admin-form-grid">
  <div class="wide"><label for="title">Tên sản phẩm / dịch vụ *</label><input id="title" name="title" maxlength="180" required value="<?=e((string)($v['title'] ?? ''))?>"></div>
  <div><label for="category">Danh mục</label><input id="category" name="category" maxlength="100" value="<?=e((string)($v['category'] ?? ''))?>" placeholder="Ứng dụng, Proxy, Dịch vụ..."></div>
  <div><label for="price">Giá hiển thị</label><input id="price" name="price" maxlength="100" value="<?=e((string)($v['price'] ?? ''))?>" placeholder="Liên hệ / 50.000 VNĐ"></div>
  <div class="wide"><label for="external_url">Liên kết dịch vụ (HTTPS)</label><input id="external_url" name="external_url" maxlength="2048" value="<?=e((string)($v['external_url'] ?? ''))?>" placeholder="https://..."></div>
  <div class="wide"><label for="description">Mô tả chi tiết *</label><textarea id="description" name="description" maxlength="12000" required><?=e((string)($v['description'] ?? ''))?></textarea></div>
  <div><label for="status">Trạng thái</label><select id="status" name="status"><option value="published" <?=($v['status'] ?? 'published')==='published'?'selected':''?>>Công khai</option><option value="draft" <?=($v['status'] ?? '')==='draft'?'selected':''?>>Bản nháp</option></select></div>
  <div><label for="screenshots">Ảnh thư viện (tối đa 8 ảnh/lần)</label><input id="screenshots" type="file" name="screenshots[]" accept="image/jpeg,image/png,image/webp,image/gif" multiple><small class="store-inline-hint">JPG, PNG, WEBP hoặc GIF; tối đa 5 MB mỗi ảnh.</small></div>
</div>
