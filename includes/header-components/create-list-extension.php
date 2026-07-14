<?php
declare(strict_types=1);

if (!mg_is_authenticated()) {
    return;
}
?>
<template id="mg-create-list-rail-template">
  <a class="mg-create-center-rail-link" href="/lists.php?action=create" data-create-menu-option="contact_list" data-create-tool-key="list" data-create-inline-target="list" aria-controls="mg-create-center-list">
    <span class="mg-create-menu-icon is-list" aria-hidden="true"><svg viewBox="0 0 24 24" focusable="false"><path d="M8 6h12M8 12h12M8 18h12"/><circle cx="4" cy="6" r="1.5"/><circle cx="4" cy="12" r="1.5"/><circle cx="4" cy="18" r="1.5"/></svg></span>
    <span class="mg-create-menu-copy"><strong>List</strong></span>
  </a>
</template>
<template id="mg-create-list-card-template">
  <a class="mg-create-center-card" href="/lists.php?action=create" data-create-menu-option="contact_list" data-create-tool-key="list" data-create-inline-target="list" aria-controls="mg-create-center-list">
    <span class="mg-create-menu-icon is-list" aria-hidden="true"><svg viewBox="0 0 24 24" focusable="false"><path d="M8 6h12M8 12h12M8 18h12"/><circle cx="4" cy="6" r="1.5"/><circle cx="4" cy="12" r="1.5"/><circle cx="4" cy="18" r="1.5"/></svg></span>
    <span class="mg-create-menu-copy"><strong>List</strong><small>Create a group for family, friends, coworkers, birthdays, or gifting plans.</small></span>
    <span class="mg-create-menu-arrow" aria-hidden="true">→</span>
  </a>
</template>
<template id="mg-create-list-view-template">
  <section class="mg-create-center-view" id="mg-create-center-list" data-create-center-view="list" hidden>
    <div class="mg-create-inline-head"><div><span class="mg-create-menu-eyebrow">Personal contacts</span><h3>Create a list</h3><p>Organize people and occasions for reminders, recommendations, and gifting plans.</p></div><a href="/lists.php">Open My Lists</a></div>
    <div class="mg-create-inline-success" data-create-inline-success="list" hidden><strong>List created successfully.</strong><p data-create-success-message></p><div><a href="/lists.php" data-create-success-link>Open list</a><button type="button" data-create-inline-reset="list">Create another</button></div></div>
    <form class="mg-create-inline-form" data-create-inline-form="list">
      <div class="mg-create-form-grid mg-create-form-grid-2"><label>List name<input name="name" maxlength="160" required placeholder="Family"></label><label>List type<select name="list_type"><option value="family">Family</option><option value="friends">Friends</option><option value="coworkers">Coworkers</option><option value="clients">Clients</option><option value="birthday">Birthday gifts</option><option value="holiday">Holiday gifts</option><option value="community">Community</option><option value="team">Team</option><option value="vip">VIP</option><option value="custom">Custom</option></select></label></div>
      <label>Description<textarea name="description" maxlength="1000" rows="4" placeholder="Who belongs here and what should your gifting agent remember?"></textarea></label>
      <label>Icon / category<select name="icon_key"><option value="people">People</option><option value="family">Family</option><option value="heart">Heart</option><option value="work">Work</option><option value="gift">Gift</option><option value="calendar">Calendar</option><option value="community">Community</option><option value="team">Team</option><option value="star">Star</option></select></label>
      <label>Initial contact search<input name="initial_contact_search" maxlength="80" placeholder="Optional — add contacts after creation"></label>
      <div class="mg-create-inline-status" data-create-inline-status="list" role="status" aria-live="polite"></div>
      <div class="mg-create-inline-actions"><button class="mg-create-submit" type="submit">Create list</button><button type="button" class="mg-create-secondary" data-create-center-home>Cancel</button></div>
    </form>
  </section>
</template>
