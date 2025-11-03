    </div> <!-- End of main content container -->
    
    <?php if (isLoggedIn()): ?>
    <!-- Footer -->
    <footer class="footer mt-5 py-3 bg-light">
        <div class="container-fluid text-center">
            <span class="text-muted">&copy; <?php echo date('Y'); ?> <?php echo APP_NAME; ?>. All rights reserved.</span>
        </div>
    </footer>
    <?php endif; ?>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    
    <!-- Custom JS -->
    <script src="<?php echo APP_URL;?>/assets/js/script.js"></script>
    
    <?php if (isset($additionalJS)): ?>
        <?php echo $additionalJS; ?>
    <?php endif; ?>
</body>
</html>
