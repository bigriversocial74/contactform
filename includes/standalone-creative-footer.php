</main>
<script src="/assets/js/microgifter.js" defer></script>
<script src="/assets/js/api-client.js" defer></script>
<script src="/assets/js/universal-header.js" defer></script>
<?php foreach (array_values(array_unique($page_scripts ?? [])) as $script): ?>
<script src="<?= mg_e((string) $script) ?>" defer></script>
<?php endforeach; ?>
</body>
</html>
