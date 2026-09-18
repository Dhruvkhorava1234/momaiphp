<?php
/**
 * Global Footer Component
 * MOMAI PLYWOOD - Core PHP
 */
$pathPrefix = $pathPrefix ?? '';
?>
        </main>
    </div>

    <!-- Global Autosearch Logic (Alpine + AJAX Fetch) -->
    <script>
        function globalAutoSearch() {
            return {
                query: '',
                results: [],
                loading: false,
                isOpen: false,
                debounceTimer: null,

                onInput() {
                    clearTimeout(this.debounceTimer);
                    const cleanQuery = this.query.trim();

                    if (cleanQuery.length < 2) {
                        this.results = [];
                        this.isOpen = false;
                        this.loading = false;
                        return;
                    }

                    this.loading = true;
                    this.isOpen = true;

                    // Debounce AJAX request by 200ms
                    this.debounceTimer = setTimeout(() => {
                        fetch('<?= $pathPrefix ?>product-search.php?q=' + encodeURIComponent(cleanQuery), {
                            headers: {
                                'Accept': 'application/json',
                                'X-Requested-With': 'XMLHttpRequest'
                            }
                        })
                        .then(response => response.json())
                        .then(data => {
                            this.results = data;
                            this.loading = false;
                            this.isOpen = true;
                        })
                        .catch(error => {
                            console.error('AutoSearch error:', error);
                            this.loading = false;
                        });
                    }, 200);
                }
            }
        }
    </script>
</body>
</html>
