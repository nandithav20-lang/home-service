<?php
$isPortal = (strpos($_SERVER['PHP_SELF'], '/customer/') !== false || 
             strpos($_SERVER['PHP_SELF'], '/provider/') !== false || 
             strpos($_SERVER['PHP_SELF'], '/admin/') !== false);
?>

<?php if ($isPortal && isset($_SESSION['user_id'])): ?>
        </main> <!-- .app-body -->
    </div> <!-- .app-main -->
    
    <!-- Mobile Sidebar Backdrop Overlay -->
    <div class="sidebar-backdrop" id="sidebar-backdrop"></div>
</div> <!-- .app-layout -->

<?php else: ?>
    </div> <!-- .container -->
</main> <!-- .main-content -->

<footer class="footer">
    <div class="container">
        <div class="grid grid-3">
            <div>
                <div class="sidebar-brand" style="margin-bottom: 0.85rem;">
                    <div class="sidebar-logo-icon"><i class="fa-solid fa-house-chimney"></i></div>
                    <span>HomeService</span>
                </div>
                <p style="font-size: 0.9rem; line-height: 1.6; color: var(--text-muted);">
                    A trusted on-demand home service platform connecting homeowners with vetted, high-quality local technicians and professionals.
                </p>
                <div style="margin-top: 1rem; color: var(--primary); font-size: 0.88rem; font-weight: 600;">
                    <i class="fa-solid fa-shield-halved"></i> Verified Technicians & Upfront Pricing
                </div>
            </div>
            <div>
                <h4 style="font-size: 1rem; margin-bottom: 0.85rem; color: var(--text-dark);">Popular Services</h4>
                <ul style="list-style: none; line-height: 2; font-size: 0.9rem;">
                    <li><a href="<?php echo $pathPrefix; ?>customer/services.php?category=1"><i class="fa-solid fa-angle-right" style="font-size:11px;margin-right:6px;"></i> Plumbing & Pipe Leaks</a></li>
                    <li><a href="<?php echo $pathPrefix; ?>customer/services.php?category=2"><i class="fa-solid fa-angle-right" style="font-size:11px;margin-right:6px;"></i> Electrical Wiring & Switches</a></li>
                    <li><a href="<?php echo $pathPrefix; ?>customer/services.php?category=3"><i class="fa-solid fa-angle-right" style="font-size:11px;margin-right:6px;"></i> Deep Home & Kitchen Cleaning</a></li>
                    <li><a href="<?php echo $pathPrefix; ?>customer/services.php?category=4"><i class="fa-solid fa-angle-right" style="font-size:11px;margin-right:6px;"></i> Carpentry & Furniture Repair</a></li>
                </ul>
            </div>
            <div>
                <h4 style="font-size: 1rem; margin-bottom: 0.85rem; color: var(--text-dark);">Quick Contact</h4>
                <p style="font-size: 0.9rem; margin-bottom: 6px;"><i class="fa-solid fa-location-dot" style="margin-right:8px;color:var(--primary);"></i> Bengaluru, Karnataka, India</p>
                <p style="font-size: 0.9rem; margin-bottom: 6px;"><i class="fa-solid fa-envelope" style="margin-right:8px;color:var(--primary);"></i> support@homeservice.com</p>
                <p style="font-size: 0.9rem;"><i class="fa-solid fa-phone" style="margin-right:8px;color:var(--primary);"></i> +91 98765 43210</p>
            </div>
        </div>
        <div class="footer-bottom">
            <p>&copy; <?php echo date('Y'); ?> HomeService. All rights reserved. Clean light-theme commercial service platform.</p>
        </div>
    </div>
</footer>
<?php endif; ?>

<script src="<?php echo $pathPrefix; ?>assets/js/main.js"></script>
</body>
</html>
