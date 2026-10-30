/* TALA sample form filling. This helper never saves or submits a form. */
(() => {
    'use strict';
    if (window.talaSampleForms) return;
    window.talaSampleForms = true;
    const seen = new WeakMap();
    const fieldVisibility = new WeakMap();
    const pendingFiles = new WeakSet();
    const attachmentCache = new Map();
    const assetBase = new URL('.', document.currentScript?.src || location.href);
    const attachments = [
        { extension: 'pdf', type: 'application/pdf', path: 'output/pdf/sample-attachment.pdf' },
        { extension: 'png', type: 'image/png', path: 'assets/sample/sample-attachment.png' },
        { extension: 'jpg', type: 'image/jpeg', path: 'assets/sample/sample-attachment.jpg' },
        { extension: 'webp', type: 'image/webp', path: 'assets/sample/sample-attachment.webp' },
    ];
    const supported = new Set(['text', 'email', 'tel', 'url', 'number', 'date', 'datetime-local', 'month', 'time', 'week', 'file']);
    const sensitive = /password|passwd|secret|token|csrf|otp|credit.?card|card.?number|cvv|philsys|philhealth|bank.?account/i;
    const visible = node => Boolean(node.getClientRects().length) && getComputedStyle(node).visibility !== 'hidden';
    const fieldVisible = field => visible(field) || (field.type === 'file' && visible(field.closest('label') || field.parentElement));
    const fields = form => Array.from(form.elements).filter(field =>
        field.matches('input, textarea, select') && !field.disabled && !field.readOnly &&
        !field.closest('[data-sample-fill="off"]') && fieldVisible(field) &&
        (field.type === 'file' || !sensitive.test(`${field.name} ${field.id} ${field.autocomplete}`)) &&
        (field.tagName !== 'INPUT' || supported.has(field.type) || ['radio', 'checkbox'].includes(field.type)));

    function writableForm(form) {
        if (form.closest('[data-sample-fill="off"]')) return false;
        const action = new URL(form.getAttribute('action') || location.href, location.href).pathname;
        if (/login|logout|sign.?in/i.test(action)) return false;
        // Search/filter forms and small confirmation forms should retain their native behavior.
        const editable = fields(form).filter(field => !['radio', 'checkbox'].includes(field.type));
        return (form.getAttribute('method') || 'get').toLowerCase() === 'post' &&
            (editable.length >= 2 || editable.some(field => field.tagName === 'TEXTAREA' || (field.type === 'file' && attachmentFor(field))));
    }
    function attachmentFor(field) {
        const accept = (field.accept || '').toLowerCase().split(',').map(value => value.trim()).filter(Boolean);
        const candidates = attachments.filter(file => !accept.length || accept.some(value =>
            value === '*/*' || value === file.type || value === `.${file.extension}` ||
            (value === '.jpeg' && file.extension === 'jpg') || (value === 'image/*' && file.type.startsWith('image/'))));
        const key = `${field.name} ${field.id}`;
        if (/photo|image|picture|avatar|portrait/i.test(key)) {
            const image = candidates.find(file => file.type.startsWith('image/'));
            if (image && /profile|id_photo|avatar|portrait|achiever|^photo\s/i.test(key)) {
                return { ...image, path: `assets/sample/youth-profile.${image.extension}` };
            }
            return image || candidates[0];
        }
        return candidates[0];
    }
    async function fillAttachment(field) {
        if (field.files.length || pendingFiles.has(field) || typeof DataTransfer === 'undefined') return;
        const attachment = attachmentFor(field);
        if (!attachment) return;
        pendingFiles.add(field);
        try {
            if (!attachmentCache.has(attachment.path)) {
                const request = fetch(new URL(attachment.path, assetBase)).then(response => {
                    if (!response.ok) throw new Error('Sample attachment unavailable');
                    return response.blob();
                });
                attachmentCache.set(attachment.path, request);
                request.catch(() => attachmentCache.delete(attachment.path));
            }
            const blob = await attachmentCache.get(attachment.path);
            // A manual selection made while the sample was loading always wins.
            if (!field.isConnected || field.disabled || field.files.length || !fieldVisible(field)) return;
            const name = (field.name || field.id || 'attachment').replace(/[^a-z0-9_-]/gi, '-');
            const transfer = new DataTransfer();
            const subject = attachment.path.includes('youth-profile') ? 'ana-reyes' : attachment.type.startsWith('image/') ? 'youth-workshop' : 'youth-program';
            transfer.items.add(new File([blob], `${name}-${subject}.${attachment.extension}`, { type: attachment.type }));
            field.files = transfer.files;
            // Selecting the sample does not trigger upload/change handlers or save actions.
        } catch (_) {
            // Keep the normal file picker available if a sample cannot be loaded.
        } finally {
            pendingFiles.delete(field);
        }
    }
    function label(field) {
        const text = Array.from(field.labels || [], item => item.textContent).join(' ') ||
            field.closest('div')?.querySelector('label')?.textContent || '';
        return `${field.name} ${field.id} ${text} ${field.placeholder || ''}`.toLowerCase();
    }
    function dateValue(days = 0, years = 0) {
        const date = new Date();
        date.setFullYear(date.getFullYear() + years);
        date.setDate(date.getDate() + days);
        return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`;
    }
    function formContent() {
        const page = location.pathname.toLowerCase();
        if (/mom|assembly/.test(page)) return {
            title: 'Fourth Quarter Katipunan ng Kabataan General Assembly',
            description: 'Present the youth council accomplishment and financial reports, review upcoming programs, and gather recommendations from young residents. Record attendance, agreed actions, and assigned committee responsibilities.',
        };
        if (/disclosure/.test(page)) return {
            title: 'Annual Budget and Youth Development Report 2026',
            description: 'A consolidated report of approved youth development allocations, implemented activities, and supporting financial records for public disclosure and review.',
        };
        if (/scholarship|services/.test(page)) return {
            title: 'Tumauini Youth Educational Assistance Program',
            description: 'Educational assistance for qualified Tumauini residents enrolled in senior high school or college. Applicants must submit proof of enrollment, a barangay residency certificate, and their latest school grades.',
        };
        if (/resolution/.test(page)) return {
            title: 'Resolution Approving the Youth Leadership Workshop',
            description: 'A resolution authorizing the conduct of a youth leadership workshop, allocating funds for training materials and meals, and assigning the SK committee to coordinate implementation and reporting.',
        };
        if (/budget|voucher|ppa_request/.test(page)) return {
            title: 'Youth Leadership and Community Development Program',
            description: 'Training materials, meals, and workshop supplies for 50 youth participants. The activity develops leadership, teamwork, and community project planning, with attendance and liquidation documents prepared after implementation.',
        };
        if (/poll|civic/.test(page)) return {
            title: 'Youth Program Priorities Consultation',
            description: 'Gather feedback from young residents on their preferred skills training, sports, and community service activities to guide the next barangay youth development plan.',
        };
        if (/eco/.test(page)) return {
            title: 'Barangay Clean-Up and Tree Planting Drive',
            description: 'Youth volunteers will collect segregated waste, plant native seedlings, and discuss proper waste disposal with residents. The activity promotes environmental responsibility and cleaner community spaces.',
        };
        if (/culture/.test(page)) return {
            title: 'Outstanding Youth Volunteer Recognition',
            description: 'Recognize youth volunteers who supported barangay outreach, cultural activities, and community service projects. The program highlights their contributions and encourages continued civic participation.',
        };
        if (/safety/.test(page)) return {
            title: 'Youth Emergency Preparedness Orientation',
            description: 'Orient youth participants on evacuation procedures, basic first aid, emergency contacts, and family preparedness. Participants will join a supervised drill at the barangay evacuation center.',
        };
        if (/rights/.test(page)) return {
            title: 'Youth Rights and Protection Orientation',
            description: 'An orientation on youth rights, safe reporting channels, and available referral services, coordinated with the barangay council and local social welfare office.',
        };
        if (/project|kanban/.test(page)) return {
            title: 'Youth Learning and Digital Skills Hub',
            description: 'Establish a shared learning space with digital literacy sessions, peer tutoring, and career preparation activities for young residents. Track attendance, resources, and participant feedback throughout implementation.',
        };
        if (/tasks/.test(page)) return {
            title: 'Coordinate Youth Workshop Registration',
            description: 'Confirm the venue, prepare the attendance sheet, coordinate invitations with barangay youth leaders, and organize the training materials before the workshop.',
        };
        if (/announcement|notification|popup/.test(page)) return {
            title: 'Registration Opens for the Youth Leadership Workshop',
            description: 'Young residents of Tumauini are invited to join a leadership and community service workshop at the Municipal Multi-Purpose Hall. Contact the youth development office for the schedule and registration requirements.',
        };
        return {
            title: 'Youth Leadership and Community Service Workshop',
            description: 'A leadership workshop for 50 young residents of Tumauini covering teamwork, community service, and project planning. Participants will join guided discussions and prepare a barangay action plan at the Municipal Multi-Purpose Hall.',
        };
    }
    function sample(field) {
        const key = label(field);
        const name = (field.name || field.id || '').replace(/\[\d*\]/g, '').toLowerCase();
        const context = location.pathname.toLowerCase();
        const content = formContent();
        const values = {
            org_name: 'Tumauini Youth Volunteers Association',
            organization: 'Tumauini Youth Volunteers Association',
            organizations: 'Tumauini Youth Volunteers Association',
            provider: 'Municipal Government of Tumauini',
            president_name: 'Miguel Santos',
            gender_identity: 'Woman',
            grade_level: 'Grade 12',
            school_id: '2026-01482',
            tesda_nc: 'Computer Systems Servicing NC II',
            scholarship: 'Municipal Educational Assistance',
            programs_availed: 'Youth leadership training and educational assistance',
            activity_name: 'Youth Leadership Workshop',
            achievement: 'Led a team of 20 volunteers during the barangay clean-up and literacy outreach activities.',
            action_name: 'Community Service Participation',
            custom_badge_name: 'Community Service Champion',
            badge_text: 'Youth in Action',
            center_name: 'Tumauini Municipal Evacuation Center',
            service_name: 'Emergency Preparedness Assistance',
            folder_name: 'Youth Development Reports 2026',
            new_category: 'Budget and Accomplishment Reports',
            resolution_number: '2026-015',
            year_coverage: '2026',
            year_tag: '2026',
            manual_qr: 'TALA-YTH-2026-01482',
            youth_id: 'TALA-YTH-2026-01482',
            expected_output: '50 trained youth participants, a completed attendance sheet, and five barangay action plans.',
            objective: 'Develop youth leadership skills and produce practical community action plans through hands-on workshops.',
            eligibility: 'Tumauini residents aged 15 to 30 with proof of residency and current school enrollment.',
            question: /eco/.test(context) ? 'Which environmental activity should the barangay prioritize?' : 'Which youth development activity would you like to join?',
            answer: 'Digital skills training and community leadership workshops',
            opt1: /eco/.test(context) ? 'Tree planting' : 'Digital skills training',
            opt2: /eco/.test(context) ? 'Community clean-up' : 'Sports development',
            opt3: /eco/.test(context) ? 'Waste segregation workshop' : 'Leadership workshop',
            feedback: 'The submitted document is complete and ready for review by the assigned office.',
            admin_reply: 'Your concern has been received and referred to the youth development office for follow-up.',
            message: 'I would like to ask about the requirements and schedule for the next youth skills training program.',
            stat_label: 'Youth Participants',
            stat_value: '50',
            title_main: 'Empowering Tumauini Youth',
            title_highlight: 'Through Community Action',
            button_text: 'View Program Details',
            remarks: 'Coordinate with the barangay youth focal person and prepare the required supporting documents.',
            reason: 'Support youth participation and strengthen community service activities in the barangay.',
            e_signature: 'Ana Santos Reyes',
        };
        // Exact field names take priority over broad label matching.
        if (Object.hasOwn(values, name) && field.type !== 'number') return values[name];
        if (name === 'options') {
            const siblings = Array.from(field.form.elements).filter(other => other.name === field.name);
            return ['Digital skills training', 'Sports development', 'Leadership workshop'][siblings.indexOf(field) % 3];
        }
        if (/image_url|icon_url/.test(name)) return new URL('assets/sample/sample-attachment.png', assetBase).href;
        if (/application_link|button_link|^link$|^url$/.test(name)) return 'https://example.com/youth-program';
        if (field.type === 'email') return 'ana.reyes@example.com';
        if (field.type === 'tel' || /contact|phone|mobile/.test(key)) return '09171234567';
        if (field.type === 'url') return 'https://example.com/youth-program';
        if (['date', 'datetime-local', 'month', 'week', 'time'].includes(field.type)) {
            const pastActivity = /acquired|awarded|issued|passed|transaction|withdrawal|attended/.test(key);
            let date = dateValue(/end|deadline|due|return/.test(key) ? 7 : /birth/.test(key) ? 0 : pastActivity ? -7 : 1, /birth/.test(key) ? -22 : 0);
            if (field.min && date < field.min.slice(0, 10)) date = field.min.slice(0, 10);
            if (field.max && date > field.max.slice(0, 10)) date = field.max.slice(0, 10);
            if (field.type === 'time') return field.min || '09:00';
            if (field.type === 'month') return date.slice(0, 7);
            if (field.type === 'week') return field.min || `${new Date().getFullYear()}-W44`;
            return field.type === 'datetime-local' ? `${date}T09:00` : date;
        }
        if (field.type === 'number') {
            let number = /age/.test(key) ? 22 : /amount|budget|cost|price/.test(key) ? 25000 :
                /income|salary/.test(key) ? 12000 : /participant|capacity|limit/.test(key) ? 50 :
                /household|member/.test(key) ? 4 : /year/.test(key) ? new Date().getFullYear() : 10;
            const minimum = field.min === '' ? 0 : Number(field.min);
            const maximum = field.max === '' ? Infinity : Number(field.max);
            const step = !field.step || field.step === 'any' ? 1 : Number(field.step);
            number = Math.min(maximum, Math.max(minimum, number));
            if (step > 0) number = minimum + Math.floor((number - minimum) / step) * step;
            return String(Number(number.toFixed(6)));
        }
        if (/email/.test(key)) return 'ana.reyes@example.com';
        if (/first.?name/.test(key)) return 'Ana';
        if (/middle.?name/.test(key)) return 'Santos';
        if (/last.?name|surname/.test(key)) return 'Reyes';
        if (/father/.test(key)) return 'Roberto Reyes';
        if (/mother/.test(key)) return 'Maria Reyes';
        if (/guardian/.test(key)) return 'Maria Reyes';
        if (/full.?name|applicant|beneficiary|payee|recipient/.test(key)) return 'Ana Santos Reyes';
        if (/username/.test(key)) return 'ana.reyes2026';
        if (/address|street|purok/.test(key)) return 'Purok 2, Barangay District I, Tumauini, Isabela';
        if (/location|venue/.test(key)) return 'Tumauini Municipal Multi-Purpose Hall';
        if (/school|university/.test(key) && !/\bid\b/.test(key)) return 'Tumauini National High School';
        if (/course/.test(key)) return 'Bachelor of Science in Information Technology';
        if (/occupation|position/.test(key)) return 'Youth Volunteer';
        if (/employer|organization/.test(key)) return 'Tumauini Youth Volunteers';
        if (/duration|period/.test(key)) return 'November - December 2026';
        if (/asset.?name/.test(key)) return 'Portable Projector';
        if (/reference|tracking|voucher.?no|resolution.?no|school.?id/.test(key)) return '2026-001';
        if (/title|program|project|activity|event.?name/.test(key) && field.tagName !== 'TEXTAREA') {
            return content.title;
        }
        if (field.tagName === 'TEXTAREA' || /description|details|narrative|purpose|objective|reason|remarks/.test(key)) {
            return content.description;
        }
        if (/name/.test(key)) return 'Ana Santos Reyes';
        if (/skill/.test(key)) return 'Public speaking and community organizing';
        if (/barangay/.test(key)) return 'District I';
        if (/content|body|topic/.test(key)) return content.description;
        if (/year/.test(key)) return String(new Date().getFullYear());
        if (/age/.test(key)) return '22';
        // Unknown labels remain available for manual entry instead of receiving unrelated text.
        return '';
    }
    function valid(field, value) {
        const probe = field.cloneNode(true);
        probe.required = false;
        probe.value = value;
        return probe.value === value && (!probe.willValidate || probe.validity.valid);
    }
    function assign(field, value) {
        if (['radio', 'checkbox'].includes(field.type)) field.checked = true;
        else field.value = value;
        // Do not dispatch input/change: existing handlers may save or submit immediately.
    }
    function fill(form, targets = fields(form)) {
        if (!writableForm(form)) return;
        for (const field of targets) {
            if (field.type === 'file') {
                fillAttachment(field);
            } else if (['radio', 'checkbox'].includes(field.type)) {
                // Leave consent/approval checkboxes for the person completing the form.
                if (field.type === 'checkbox' && !/skills|organizations|programs|gov_services/.test(field.name)) continue;
                const group = fields(form).filter(other => other.name === field.name && other.type === field.type);
                if (group.some(other => other.checked)) continue;
                assign(field, field.value);
            } else if (field.tagName === 'SELECT') {
                if (field.value) continue;
                const available = Array.from(field.options).filter(option => option.value && !option.disabled && !option.parentElement.disabled);
                const choice = available.find(option => /upcoming|pending|draft|good|female|single|student/i.test(option.textContent)) || available[0];
                if (choice) assign(field, choice.value);
            } else if (!field.value.trim()) {
                let value = sample(field);
                if (field.maxLength > 0) value = value.slice(0, field.maxLength);
                if (valid(field, value)) assign(field, value);
            }
        }
    }
    function scan() {
        for (const form of document.forms) {
            const shown = visible(form);
            if (shown && writableForm(form)) {
                const newlyShown = fields(form).filter(field => !fieldVisibility.get(field));
                if (!seen.get(form)) fill(form);
                else if (newlyShown.length) fill(form, newlyShown);
            }
            Array.from(form.elements).forEach(field => fieldVisibility.set(field, fieldVisible(field)));
            seen.set(form, shown);
        }
    }
    let pending = false;
    new MutationObserver(() => {
        if (pending) return;
        pending = true;
        requestAnimationFrame(() => { pending = false; scan(); });
    }).observe(document.body, { subtree: true, childList: true, attributes: true, attributeFilter: ['class', 'style', 'hidden', 'open'] });
    scan();
})();
