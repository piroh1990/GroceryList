# Registered Users – Feature Plan

This document outlines the additional functionality that **registered users**
will have compared to anonymous (guest) visitors. The app continues to work
without an account, but signing up unlocks the features below.

---

## Current State (Implemented)

| Feature | Guest | Registered |
|---|:---:|:---:|
| Create a list | ✅ | ✅ |
| Add / check / delete items | ✅ | ✅ |
| Share a list via URL | ✅ | ✅ |
| Recent lists (localStorage) | ✅ | ✅ |
| List ownership (DB-linked) | ❌ | ✅ |
| `my_lists.php` API endpoint | ❌ | ✅ |

---

## Phase 1 – My Lists Dashboard

**Goal:** Give registered users a persistent, server-side "My Lists" view that
replaces the localStorage-only sidebar.

- **My Lists page / panel**: Fetch owned lists from `api/my_lists.php` and
  display them in the sidebar (or a dedicated page) with list name, item count,
  and last-updated timestamp.
- **Claim anonymous lists**: Allow a logged-in user to "claim" an anonymous
  list they have open by clicking an "Add to my account" button. This sets
  `owner_id` on the list row.
- **Delete a list**: Only the owner can permanently delete a list. Add
  `api/delete_list.php` with ownership verification.
- **Sort & search**: Let users sort their lists by name, date, or activity, and
  add a simple text filter.

---

## Phase 2 – Collaboration & Permissions

**Goal:** Let the list owner control who can view or edit their lists.

- **Access roles**: Introduce a `list_collaborators` join table with columns
  `list_id`, `user_id`, and `role` (`viewer` | `editor`).
- **Invite by email / username**: The owner can invite other registered users to
  collaborate on a list, choosing whether they can only view or also edit.
- **Read-only mode**: Viewers see the list but cannot add, check, or delete
  items. The UI hides mutation controls for non-editors.
- **Remove collaborator**: The owner can revoke access at any time.
- **Public / private toggle**: The owner can mark a list as *private* (only
  owner + explicit collaborators can access it, even with the URL) or *public*
  (anyone with the link, the current default).

---

## Phase 3 – Profile & Account Management

**Goal:** Let users manage their account and preferences.

- **Profile page** (`/profile`): Display username, email, account creation
  date, total lists, total items.
- **Change password**: Requires current password + new password confirmation.
- **Change email**: With email verification (send a confirmation link).
- **Delete account**: Soft-delete or full purge with confirmation. Orphan owned
  lists (set `owner_id = NULL`) or delete them based on user choice.
- **Avatar / display name**: Optional personal branding shown in list headers
  and collaboration views.

---

## Phase 4 – Activity & Notifications

**Goal:** Keep registered users informed about changes to their lists.

- **Activity log**: Record who added, checked, or deleted items. Show a per-list
  activity feed with timestamps and usernames (or "Anonymous").
- **Email notifications** (opt-in): Notify the owner when a collaborator makes
  changes. Configurable frequency: instant, daily digest, or off.
- **In-app notification badge**: Show a small badge on the "My Lists" button
  when lists the user owns have been updated since their last visit.

---

## Phase 5 – Advanced List Features (Registered Only)

**Goal:** Premium-tier features that reward signing up.

- **List templates**: Save a list as a reusable template (e.g., "Weekly
  Groceries") and create new lists pre-populated with those items.
- **Categories / aisles**: Group items by category (Produce, Dairy, etc.) with
  drag-and-drop reordering. Categories are saved per-user.
- **Quantity & notes**: Add quantity (e.g., "2 lbs") and a note field to each
  item. Anonymous lists keep the simple single-field UX.
- **Price tracking**: Optionally enter prices per item. Show a running total for
  the list.
- **List history / undo**: Keep a short changelog so the owner can undo recent
  bulk changes (e.g., accidental "check all").
- **Export / import**: Export a list as CSV, JSON, or plain text. Import items
  from a file or pasted text.

---

## Phase 6 – Security Hardening

**Goal:** Protect user accounts and data.

- **CSRF tokens**: Add a per-session CSRF token to all POST endpoints.
- **Rate limiting**: Throttle login and registration attempts (e.g., 5 per
  minute per IP) to prevent brute-force attacks.
- **Password reset**: "Forgot password" flow with a time-limited token sent via
  email.
- **Remember me**: Optional long-lived "remember me" cookie with a secure,
  rotating token stored in a `user_sessions` table.
- **Two-factor authentication (2FA)**: Optional TOTP-based 2FA for accounts
  that want extra security.

---

## Implementation Priority

| Priority | Phase | Effort |
|---|---|---|
| 🔴 High | Phase 1 – My Lists Dashboard | Small |
| 🔴 High | Phase 6 – CSRF & rate limiting | Small |
| 🟠 Medium | Phase 2 – Collaboration & Permissions | Medium |
| 🟠 Medium | Phase 3 – Profile & Account Management | Medium |
| 🟡 Low | Phase 4 – Activity & Notifications | Large |
| 🟡 Low | Phase 5 – Advanced List Features | Large |

---

## Database Changes Summary

The following new tables / columns will be needed across all phases:

```
users                    (✅ already created)
grocery_lists.owner_id   (✅ already created)

list_collaborators       (Phase 2)
    id, list_id, user_id, role, created_at

user_sessions            (Phase 6)
    id, user_id, token, expires_at, created_at

list_activity            (Phase 4)
    id, list_id, user_id, action, detail, created_at

list_templates           (Phase 5)
    id, user_id, template_name, created_at

template_items           (Phase 5)
    id, template_id, item_name, category, quantity, notes
```
