<?php declare(strict_types=1); ?>
<?php if (($layout ?? 'store') === 'website'): ?>
</main>
<footer class="footer"><div class="container footer-inner"><div class="brand"><span class="brand-mark">H</span><span>HENDY<small>CYBERTECH</small></span></div><p>© <?=date('Y')?> HENDY Cybertech. All rights reserved.</p></div></footer>
</body></html>
<?php else: ?>
</main>
<footer class="store-footer"><span>© <?=date('Y')?> HENDY Cybertech</span><span>Website · App Store · Dịch vụ mạng · Telegram Bot</span></footer>
</div></div></body></html>
<?php endif; ?>
