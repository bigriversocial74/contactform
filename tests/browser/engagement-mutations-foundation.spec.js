const { test, expect } = require('@playwright/test');

const profilePayload = {
  ok: true,
  data: {
    profile: {
      id: '11111111-1111-4111-8111-111111111111',
      slug: 'phoenix-maker',
      display_name: 'Phoenix Maker',
      headline: 'Local gifts made in Phoenix',
      biography: 'Small-batch products and meaningful local experiences.',
      avatar_url: null,
      cover_url: null,
      location_label: 'Phoenix, AZ',
      website_url: null,
      profile_type: 'creator',
      visibility: 'public',
      availability: { is_owner: false, is_preview: false },
    },
    links: [],
    sections: [],
    storefront: null,
    products: { items: [], next_cursor: null, has_more: false, limit: 6 },
    posts: {
      items: [{
        id: '22222222-2222-4222-8222-222222222222',
        type: 'simple',
        headline: 'A Phoenix update',
        body: 'Fresh local gifts are ready.',
        media: [],
        visibility: 'public',
        published_at: '2026-06-14 12:00:00',
        engagement: { comments: 1, reactions: 2, shares: 0 },
      }],
      next_cursor: null,
      has_more: false,
      limit: 6,
    },
    subscription_plans: { items: [], next_cursor: null, has_more: false, limit: 6 },
    tip: {
      available: true,
      target: { type: 'profile', id: '11111111-1111-4111-8111-111111111111' },
    },
    social_counts: { followers: 14, supporters: 3, published_products: 0 },
    relationship: {
      authenticated: true,
      can_follow: true,
      following: false,
      muted: false,
      blocking: false,
      followers: 14,
    },
  },
};

async function mockProfile(page) {
  await page.route('**/api/public/profile.php?**', route => route.fulfill({
    status: 200,
    contentType: 'application/json',
    body: JSON.stringify(profilePayload),
  }));
  await page.goto('/profile.php?slug=phoenix-maker');
  await page.evaluate(() => { window.Microgifter.isAuthenticated = () => true; });
}

test.describe('engagement mutations foundation', () => {
  test('follows with public profile identifiers and updates the count', async ({ page }) => {
    await page.route('**/api/social/relationship.php', async route => {
      const body = route.request().postDataJSON();
      expect(body.profile_id).toBe('11111111-1111-4111-8111-111111111111');
      expect(body.action).toBe('follow');
      expect(body.idempotency_key).toContain('profile-follow:');
      await route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({
          ok: true,
          data: {
            profile_id: body.profile_id,
            relationship: { following: true, muted: false, blocking: false, followers: 15 },
          },
        }),
      });
    });

    await mockProfile(page);
    await page.locator('[data-profile-follow]').click();
    await expect(page.locator('[data-profile-follow]')).toHaveText('Following');
    await expect(page.locator('[data-profile-followers]')).toHaveText('15');
    await expect(page.locator('[data-profile-follow-status]')).toContainText('following this profile');
  });

  test('reacts, loads comments, posts a comment, and applies comment permissions', async ({ page }) => {
    await page.route('**/api/social/engage.php', async route => {
      const body = route.request().postDataJSON();
      if (body.action === 'react') {
        await route.fulfill({
          status: 200,
          contentType: 'application/json',
          body: JSON.stringify({
            ok: true,
            data: {
              post_id: body.post_id,
              engagement: {
                comments: 1,
                reactions: 3,
                shares: 0,
                reaction_types: { like: 3, love: 0, celebrate: 0, support: 0 },
                viewer_reaction: 'like',
              },
            },
          }),
        });
        return;
      }
      if (body.action === 'comment') {
        await route.fulfill({
          status: 201,
          contentType: 'application/json',
          body: JSON.stringify({
            ok: true,
            data: {
              post_id: body.post_id,
              comment: {
                id: '44444444-4444-4444-8444-444444444444',
                body: body.body,
                status: 'visible',
                created_at: '2026-06-14 12:05:00',
                author: { display_name: 'Signed In Viewer', profile_slug: 'viewer', profile_id: 'viewer-profile' },
                permissions: { can_delete: true, can_hide: false },
              },
              engagement: { comments: 2, reactions: 3, shares: 0, viewer_reaction: 'like' },
            },
          }),
        });
        return;
      }
      await route.fulfill({ status: 422, contentType: 'application/json', body: JSON.stringify({ ok: false }) });
    });

    await page.route('**/api/public/post-engagement.php?**', route => route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify({
        ok: true,
        data: {
          post_id: '22222222-2222-4222-8222-222222222222',
          engagement: {
            comments: 1,
            reactions: 2,
            shares: 0,
            reaction_types: { like: 2, love: 0, celebrate: 0, support: 0 },
            viewer_reaction: null,
          },
          comments: {
            items: [{
              id: '33333333-3333-4333-8333-333333333333',
              body: '<script>not executable</script>',
              status: 'visible',
              created_at: '2026-06-14 12:01:00',
              author: { display_name: 'Commenter', profile_slug: 'commenter', profile_id: 'commenter-profile' },
              permissions: { can_delete: false, can_hide: true },
            }],
            next_cursor: null,
            has_more: false,
            limit: 20,
          },
          permissions: { authenticated: true, can_comment: true, is_post_owner: true },
        },
      }),
    }));

    await mockProfile(page);
    const card = page.locator('[data-post-id="22222222-2222-4222-8222-222222222222"]');
    await card.locator('[data-post-reaction="like"]').click();
    await expect(card.locator('[data-post-reaction="like"]')).toHaveAttribute('aria-pressed', 'true');
    await expect(card.locator('[data-post-stat="reactions"]')).toContainText('3 reactions');

    await card.locator('[data-post-comments]').click();
    await expect(card.locator('[data-comment-list] .mg-profile-comment')).toHaveCount(1);
    await expect(card.locator('[data-comment-list]')).toContainText('<script>not executable</script>');
    await expect(card.locator('script')).toHaveCount(0);
    await expect(card.locator('[data-comment-action="comment_hide"]')).toBeVisible();

    await card.locator('[name="comment_body"]').fill('A new local comment');
    await card.locator('[data-comment-form]').evaluate(form => form.requestSubmit());
    await expect(card.locator('[data-comment-list] .mg-profile-comment')).toHaveCount(2);
    await expect(card.locator('[data-comment-list]')).toContainText('A new local comment');
    await expect(card.locator('[data-post-stat="comments"]')).toContainText('2 comments');
  });

  test('creates and confirms a card-funded tip through the server authority', async ({ page }) => {
    await page.route('**/api/tips/create.php', async route => {
      const body = route.request().postDataJSON();
      expect(body.target_reference).toBe('11111111-1111-4111-8111-111111111111');
      expect(body.funding_type).toBe('stripe');
      await route.fulfill({
        status: 201,
        contentType: 'application/json',
        body: JSON.stringify({
          ok: true,
          data: {
            tip_id: '55555555-5555-4555-8555-555555555555',
            status: 'requires_action',
            client_secret: 'pi_test_secret',
            provider_payment_id: 'pi_test_tip',
            amount_cents: body.amount_cents,
            currency: 'USD',
          },
        }),
      });
    });
    await page.route('**/api/tips/confirm.php', async route => {
      const body = route.request().postDataJSON();
      expect(body.tip_id).toBe('55555555-5555-4555-8555-555555555555');
      await route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({
          ok: true,
          data: {
            tip_id: body.tip_id,
            status: 'posted',
            posted: true,
            duplicate: false,
            payment_intent_id: '66666666-6666-4666-8666-666666666666',
          },
        }),
      });
    });

    await mockProfile(page);
    await page.locator('[name="tip_amount"]').fill('10');
    await page.locator('[name="tip_funding"]').selectOption('stripe');
    await page.locator('[data-profile-tip-form]').evaluate(form => form.requestSubmit());
    await expect(page.locator('[data-profile-tip-confirmation]')).toBeVisible();
    await expect(page.locator('[data-profile-tip-status]')).toContainText('Complete authorization');
    await page.locator('[data-profile-tip-confirm]').click();
    await expect(page.locator('[data-profile-tip-status]')).toContainText('confirmed and posted');
    await expect(page.locator('[data-profile-tip-confirmation]')).toBeHidden();
  });

  test('keeps engagement controls usable on mobile', async ({ page }) => {
    await page.setViewportSize({ width: 390, height: 844 });
    await mockProfile(page);
    const card = page.locator('[data-post-id="22222222-2222-4222-8222-222222222222"]');
    await expect(card.locator('[data-post-reaction="like"]')).toBeVisible();
    const columns = await card.locator('.mg-profile-post-actions').evaluate(element => getComputedStyle(element).gridTemplateColumns);
    expect(columns.split(' ').length).toBe(2);
    await expect(page.locator('[data-profile-tip-card]')).toBeVisible();
  });
});
