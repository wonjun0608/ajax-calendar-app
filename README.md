## Overview
This project is a full-stack calendar web application that users can
register, log in, and manage events dynamically. The application uses
JavaScript and AJAX to handle all interactions without reloading the page,
giving a smooth, single page experience.

## Key Features
- Month-by-month calendar view with unlimited navigation
- User registration and authentication
- Create, edit, and delete events
- Events are visible only to the authenticated user
- No page reloads (AJAX-based interactions)

## Extended Features

### Event Tagging & Filtering
Events can be categorized using tags such as **Work, Meeting, Event,** and **Other**.
If no tag is selected, a default tag (**Work**) is assigned automatically.
Users can dynamically filter events by tag using checkbox controls, making it
easy to focus on specific types of activities.

### Group Events
Users can create shared group events that appear on multiple users’ calendars.
Group events are marked with **[Group]** and are editable only by the event creator
to maintain consistency. Participants may remove the event from their own calendar
without affecting others.

### Calendar Sharing
Users can share their entire calendar with other users by username.
Shared users can view all events from the owner’s calendar within their own
calendar view.

### Event Coloring
Events support customizable colors (**blue, green, yellow, red, purple**) for
better visual organization. A default color (**blue**) is applied if none is selected.
For group events, color changes made by the creator automatically propagate
to all participants’ calendars.

## Technical Highlights
- Client-side logic implemented in JavaScript
- Server-side APIs handle authentication and event management
- Session-based authorization to prevent unauthorized access
- All user and event data stored in a relational database
- Secure design: the server determines the active user from the session
  rather than trusting client-provided identifiers

## Tech Stack
- JavaScript (Frontend & AJAX)
- PHP (Server-side)
- MySQL (Database)
- HTML / CSS
