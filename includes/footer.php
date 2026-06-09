</div>
</main>

<!-- Global Loading Overlay -->
<div id="loading-overlay" class="hidden fixed inset-0 z-[100] flex items-center justify-center bg-black/40 backdrop-blur-sm">
    <div class="bg-white p-8 rounded-2xl shadow-2xl border border-outline-variant flex flex-col items-center gap-4">
        <div class="w-12 h-12 border-4 border-[#005abe]/30 border-t-primary rounded-full animate-spin"></div>
        <p class="text-on-surface font-medium animate-pulse">Processing Action...</p>
    </div>
</div>

<!-- Global Status Modal -->
<?php if (isset($_SESSION['app_modal'])): 
    $modal = $_SESSION['app_modal'];
    unset($_SESSION['app_modal']);
?>
<div id="status-modal" class="fixed inset-0 z-[110] flex items-center justify-center p-4 bg-black/40 backdrop-blur-sm transition-opacity duration-300">
    <div class="bg-white max-w-sm w-full rounded-2xl shadow-2xl border border-outline-variant p-8 flex flex-col items-center text-center animate-in fade-in zoom-in duration-300">
        <?php if (($modal['type'] ?? 'success') === 'success'): ?>
            <div class="w-20 h-20 bg-green-100 rounded-full flex items-center justify-center mb-6">
                <span class="material-symbols-outlined text-green-600 text-5xl">check_circle</span>
            </div>
            <h3 class="text-2xl font-bold text-on-surface mb-2">Berjaya!</h3>
        <?php else: ?>
            <div class="w-20 h-20 bg-red-100 rounded-full flex items-center justify-center mb-6">
                <span class="material-symbols-outlined text-red-600 text-5xl">error</span>
            </div>
            <h3 class="text-2xl font-bold text-on-surface mb-2">Ralat!</h3>
        <?php endif; ?>
        
        <p class="text-on-surface-variant mb-8 leading-relaxed"><?= htmlspecialchars($modal['message'] ?? 'Tindakan telah diproses.') ?></p>
        
        <button onclick="document.getElementById('status-modal').remove()" class="w-full py-4 bg-[#005abe] text-white rounded-xl font-bold hover:bg-primary-fixed-dim transition-all shadow-lg active:scale-95">
            Tutup
        </button>
    </div>
</div>
<?php endif; ?>

<script>
    // Global function to show loading
    function showLoading() {
        document.getElementById('loading-overlay').classList.remove('hidden');
    }

    // Auto-attach loading to all forms in admin
    document.querySelectorAll('form').forEach(form => {
        if (!form.hasAttribute('data-no-loading')) {
            form.addEventListener('submit', function() {
                // For files or complex forms, you might want to delay this
                showLoading();
            });
        }
    });
</script>

<script>
    // ── Page entrance stagger ──────────────────────────────────────────────────
    (function () {
        // Only animate visual block elements — skip <script>, <style>, <link> etc.
        var VISUAL = ['DIV','FORM','SECTION','ARTICLE','TABLE','HEADER','NAV','ASIDE'];
        var pc = document.getElementById('page-content');
        if (pc) {
            var visualChildren = Array.from(pc.children).filter(function (el) {
                return VISUAL.indexOf(el.tagName) !== -1;
            });
            visualChildren.forEach(function (el, i) {
                el.classList.add('page-item');
                el.style.animationDelay = (0.05 + i * 0.09) + 's';
            });
        }

        // Stagger tbody rows (cap at 30 rows for performance)
        document.querySelectorAll('tbody tr').forEach(function (tr, i) {
            if (i < 30) {
                tr.style.animationDelay = (0.18 + i * 0.03) + 's';
            }
        });
    })();
</script>

</body>
</html>


