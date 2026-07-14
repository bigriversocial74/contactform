<?php declare(strict_types=1); ?>

      <div class="mg-agent-tool-modal" data-personal-agent-dialog="plan" aria-hidden="true" role="dialog" aria-modal="true" aria-labelledby="personal-agent-plan-title">
        <div class="mg-agent-tool-backdrop" data-close-agent-dialog></div>
        <div class="mg-agent-tool-dialog">
          <header><div><span>Approval-first draft</span><h2 id="personal-agent-plan-title">Create gifting plan</h2></div><button type="button" data-close-agent-dialog aria-label="Close">×</button></header>
          <form class="mg-personal-agent-dialog-form" data-personal-agent-plan-form>
            <label>Plan title<input name="title" maxlength="190" required></label>
            <label>Occasion<select name="occasion_type"><option value="general">General</option><option value="birthday">Birthday</option><option value="anniversary">Anniversary</option><option value="holiday">Holiday</option><option value="thank_you">Thank you</option><option value="recognition">Recognition</option></select></label>
            <label>Occasion label<input name="occasion_label" maxlength="160"></label>
            <label>Target date<input name="target_date" type="date"></label>
            <label>Minimum budget<input name="budget_min" type="number" min="0" step="0.01"></label>
            <label>Maximum budget<input name="budget_max" type="number" min="0" step="0.01"></label>
            <label>Currency<input name="currency" value="USD" maxlength="3"></label>
            <label class="is-wide">Notes<textarea name="notes" rows="4" maxlength="5000"></textarea></label>
            <div class="mg-form-status is-wide" data-personal-agent-plan-status role="status" aria-live="polite"></div>
            <footer class="is-wide"><button class="mg-btn mg-btn-ghost" type="button" data-close-agent-dialog>Cancel</button><button class="mg-btn mg-btn-primary" type="submit">Save draft</button></footer>
          </form>
        </div>
      </div>

      <div class="mg-agent-tool-modal" data-personal-agent-dialog="reminder" aria-hidden="true" role="dialog" aria-modal="true" aria-labelledby="personal-agent-reminder-title">
        <div class="mg-agent-tool-backdrop" data-close-agent-dialog></div>
        <div class="mg-agent-tool-dialog">
          <header><div><span>In-app reminder</span><h2 id="personal-agent-reminder-title">Create reminder</h2></div><button type="button" data-close-agent-dialog aria-label="Close">×</button></header>
          <form class="mg-personal-agent-dialog-form" data-personal-agent-reminder-form>
            <label class="is-wide">Title<input name="title" maxlength="190" required></label>
            <label>Remind me<input name="remind_at" type="datetime-local" required></label>
            <label>Reminder type<select name="reminder_type"><option value="gift_planning">Gift planning</option><option value="important_date">Important date</option><option value="follow_up">Follow up</option><option value="review_plan">Review plan</option></select></label>
            <label class="is-wide">Notes<textarea name="notes" rows="3" maxlength="2000"></textarea></label>
            <div class="mg-form-status is-wide" data-personal-agent-reminder-status role="status" aria-live="polite"></div>
            <footer class="is-wide"><button class="mg-btn mg-btn-ghost" type="button" data-close-agent-dialog>Cancel</button><button class="mg-btn mg-btn-primary" type="submit">Save reminder</button></footer>
          </form>
        </div>
      </div>

      <div class="mg-agent-tool-modal" data-personal-agent-dialog="memory" aria-hidden="true" role="dialog" aria-modal="true" aria-labelledby="personal-agent-memory-title">
        <div class="mg-agent-tool-backdrop" data-close-agent-dialog></div>
        <div class="mg-agent-tool-dialog">
          <header><div><span>Agent Memory</span><h2 id="personal-agent-memory-title">Save a reusable preference</h2></div><button type="button" data-close-agent-dialog aria-label="Close">×</button></header>
          <form class="mg-personal-agent-dialog-form" data-personal-agent-memory-form>
            <label>Category<select name="category"><option value="preference">Preference</option><option value="budget">Budget</option><option value="timing">Timing</option><option value="merchant">Merchant</option><option value="category">Gift category</option><option value="relationship">Relationship</option><option value="instruction">Instruction</option><option value="gifting_style">Gifting style</option></select></label>
            <label>Memory title<input name="title" maxlength="190" required></label>
            <label class="is-wide">What should the agent remember?<textarea name="value" rows="4" maxlength="1500" required></textarea></label>
            <div class="mg-form-status is-wide" data-personal-agent-memory-status role="status" aria-live="polite"></div>
            <footer class="is-wide"><button class="mg-btn mg-btn-ghost" type="button" data-close-agent-dialog>Cancel</button><button class="mg-btn mg-btn-primary" type="submit">Save memory</button></footer>
          </form>
        </div>
      </div>

      <div class="mg-agent-tool-modal" data-personal-agent-dialog="date" aria-hidden="true" role="dialog" aria-modal="true" aria-labelledby="personal-agent-date-title">
        <div class="mg-agent-tool-backdrop" data-close-agent-dialog></div>
        <div class="mg-agent-tool-dialog">
          <header><div><span>Important date</span><h2 id="personal-agent-date-title">Add date to a private contact</h2></div><button type="button" data-close-agent-dialog aria-label="Close">×</button></header>
          <form class="mg-personal-agent-dialog-form" data-personal-agent-date-form>
            <label class="is-wide">Private contact<select name="contact_id" data-personal-agent-date-contacts required><option value="">Choose a private contact</option></select></label>
            <label>Date type<select name="date_type"><option value="birthday">Birthday</option><option value="anniversary">Anniversary</option><option value="holiday">Holiday</option><option value="milestone">Milestone</option><option value="important_date">Important date</option></select></label>
            <label>Label<input name="label" maxlength="160" required></label>
            <label>Event date<input name="event_date" type="date" required></label>
            <label>Reminder lead time<input name="reminder_days_before" type="number" min="0" max="365" value="14"></label>
            <label class="mg-personal-agent-check is-wide"><input type="checkbox" name="repeats_annually" checked><span>Repeat every year</span></label>
            <div class="mg-form-status is-wide" data-personal-agent-date-status role="status" aria-live="polite"></div>
            <footer class="is-wide"><button class="mg-btn mg-btn-ghost" type="button" data-close-agent-dialog>Cancel</button><button class="mg-btn mg-btn-primary" type="submit">Save date</button></footer>
          </form>
        </div>
      </div>
