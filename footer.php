
<?php
// /footer.php
// Consistent footer and script imports for all pages.
?>
    </main>
    
    <footer class="footer mt-auto py-3 bg-white border-top shadow-sm">
        <div class="container-fluid px-4">
            <span class="text-muted small">© <?php echo date('Y'); ?> FlowSystmz CRM. All rights reserved. | Academy ID: <?php echo $_SESSION['academy_id'] ?? 'N/A'; ?></span>
        </div>
    </footer>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

    </body>

</html>