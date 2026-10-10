    </main>
    <footer class="site-footer">
        <div class="footer-container">
            <div class="footer-section">
                <h4><?php echo defined('SITE_NAME') ? SITE_NAME : 'EduSmart Pro'; ?></h4>
                <p>Your one‑stop platform for learning, jobs, and shopping.</p>
            </div>
            <div class="footer-section">
                <h4>Quick Links</h4>
                <ul>
                    <li><a href="products.php">Shop</a></li>
                    <li><a href="about.php">About Us</a></li>
                    <li><a href="contact.php">Contact</a></li>
                </ul>
            </div>
            <div class="footer-section">
                <h4>Follow Us</h4>
                <div class="social-icons">
                    <a href="#"><i class="fab fa-facebook"></i></a>
                    <a href="#"><i class="fab fa-twitter"></i></a>
                    <a href="#"><i class="fab fa-instagram"></i></a>
                </div>
            </div>
        </div>
        <div class="footer-bottom">
            <p>&copy; <?php echo date('Y'); ?> <?php echo defined('SITE_NAME') ? SITE_NAME : 'EduSmart Pro'; ?>. All rights reserved.</p>
        </div>
    </footer>

    <script src="<?php echo defined('BASE_URL') ? BASE_URL : ''; ?>assets/js/main.js"></script>
    <script src="<?php echo defined('BASE_URL') ? BASE_URL : ''; ?>assets/js/ai-widget.js"></script>
</body>
</html>