class CommandPalette {
    constructor() {
        this.isOpen = false;
        this.currentFolder = document.querySelector('[data-current-folder]')?.dataset.currentFolder || '0';
        this.currentPage = parseInt(document.querySelector('[data-current-page]')?.dataset.currentPage || '1');
        this.currentSort = document.querySelector('[data-current-sort]')?.dataset.currentSort || 'added';
        this.currentOrder = document.querySelector('[data-current-order]')?.dataset.currentOrder || 'desc';
        this.currentPerPage = parseInt(document.querySelector('[data-per-page]')?.dataset.perPage || '25');
        
        // Store folder data for URL generation
        this.folderData = {};
        document.querySelectorAll('[data-folder-id]').forEach(folder => {
            this.folderData[folder.dataset.folderId] = {
                name: folder.dataset.folderName,
                count: folder.dataset.folderCount
            };
        });
        
        this.setupCommands();
        this.createPalette();
        this.bindEvents();
    }

    setupCommands() {
        this.commands = [
            {
                id: 'sort-artist-asc',
                label: 'Sort by Artist (A to Z)',
                icon: '👤',
                action: () => this.navigate({ sort_by: 'artist', order: 'asc' })
            },
            {
                id: 'sort-artist-desc',
                label: 'Sort by Artist (Z to A)',
                icon: '👤',
                action: () => this.navigate({ sort_by: 'artist', order: 'desc' })
            },
            {
                id: 'sort-added-desc',
                label: 'Sort by Date Added (Newest First)',
                icon: '🕒',
                action: () => this.navigate({ sort_by: 'added', order: 'desc' })
            },
            {
                id: 'sort-added-asc',
                label: 'Sort by Date Added (Oldest First)',
                icon: '🕒',
                action: () => this.navigate({ sort_by: 'added', order: 'asc' })
            },
            {
                id: 'per-page-25',
                label: 'Show 25 items per page',
                icon: '📄',
                action: () => this.navigate({ per_page: 25 })
            },
            {
                id: 'per-page-50',
                label: 'Show 50 items per page',
                icon: '📄',
                action: () => this.navigate({ per_page: 50 })
            },
            {
                id: 'per-page-100',
                label: 'Show 100 items per page',
                icon: '📄',
                action: () => this.navigate({ per_page: 100 })
            }
        ];

        // Add folder navigation commands dynamically
        const folders = document.querySelectorAll('[data-folder-id]');
        folders.forEach(folder => {
            const folderId = folder.dataset.folderId;
            const folderName = folder.dataset.folderName;
            const folderCount = folder.dataset.folderCount;
            
            this.commands.push({
                id: `folder-${folderId}`,
                label: `Go to folder: ${folderName} (${folderCount} items)`,
                icon: '📁',
                action: () => this.navigate({ folder_id: folderId })
            });
        });
    }

    createPalette() {
        this.palette = document.createElement('div');
        this.palette.className = 'command-palette';
        this.palette.innerHTML = `
            <div class="command-palette-overlay"></div>
            <div class="command-palette-modal">
                <div class="command-palette-header">
                    <div class="command-palette-search">
                        <span class="command-palette-search-icon">🔍</span>
                        <input type="text" class="command-palette-input" placeholder="Type a command or search...">
                        <kbd class="command-palette-shortcut">ESC to close</kbd>
                    </div>
                </div>
                <div class="command-palette-results"></div>
            </div>
        `;
        document.body.appendChild(this.palette);

        this.input = this.palette.querySelector('.command-palette-input');
        this.results = this.palette.querySelector('.command-palette-results');
    }

    bindEvents() {
        // Global keyboard shortcut
        document.addEventListener('keydown', (e) => {
            if ((e.metaKey || e.ctrlKey) && e.key === 'k') {
                e.preventDefault();
                this.toggle();
            }
            if (e.key === 'Escape' && this.isOpen) {
                this.close();
            }
        });

        // Search input handling
        this.input.addEventListener('input', () => this.search());

        // Click outside to close
        this.palette.querySelector('.command-palette-overlay').addEventListener('click', () => this.close());

        // Add trigger button
        const triggerButton = document.createElement('button');
        triggerButton.className = 'command-palette-trigger btn btn-primary';
        triggerButton.innerHTML = `
            <span class="command-palette-trigger-icon">⌘</span>
            <span class="command-palette-trigger-text">Search or filter...</span>
            <kbd class="command-palette-trigger-shortcut">⌘K</kbd>
        `;
        triggerButton.addEventListener('click', () => this.toggle());
        document.querySelector('.collection-controls')?.appendChild(triggerButton);
    }

    toggle() {
        if (this.isOpen) {
            this.close();
        } else {
            this.open();
        }
    }

    open() {
        this.isOpen = true;
        this.palette.classList.add('is-open');
        this.input.value = '';
        this.input.focus();
        this.search();
    }

    close() {
        this.isOpen = false;
        this.palette.classList.remove('is-open');
    }

    search() {
        const query = this.input.value.toLowerCase();
        const filtered = this.commands.filter(cmd => 
            cmd.label.toLowerCase().includes(query)
        );

        this.results.innerHTML = filtered.map(cmd => `
            <button class="command-palette-item" data-id="${cmd.id}">
                <span class="command-palette-item-icon">${cmd.icon}</span>
                <span class="command-palette-item-label">${cmd.label}</span>
            </button>
        `).join('');

        this.results.querySelectorAll('.command-palette-item').forEach(item => {
            item.addEventListener('click', () => {
                const command = this.commands.find(cmd => cmd.id === item.dataset.id);
                if (command) {
                    command.action();
                    this.close();
                }
            });
        });
    }

    navigate(params) {
        // Get current values from data attributes
        const folder = params.folder_id || this.currentFolder;
        const sort = params.sort_by || this.currentSort;
        const order = params.order || this.currentOrder;
        const perPage = params.per_page || this.currentPerPage;
        
        // Reset to page 1 when changing folder, sort, or per_page
        const page = (params.folder_id || params.sort_by || params.order || params.per_page) ? 1 : this.currentPage;
        
        // Get folder name for URL generation
        let folderSlug;
        if (folder === '0') {
            folderSlug = 'all';
        } else if (folder === '1') {
            folderSlug = 'uncategorized';
        } else {
            const folderInfo = this.folderData[folder];
            if (folderInfo) {
                // Convert folder name to URL-friendly slug
                folderSlug = folderInfo.name.toLowerCase()
                    .replace(/[^\w\s-]/g, '') // Remove special characters
                    .replace(/\s+/g, '-')     // Replace spaces with hyphens
                    .replace(/-+/g, '-')      // Remove consecutive hyphens
                    .replace(/^-+|-+$/g, ''); // Remove leading/trailing hyphens
            } else {
                folderSlug = 'all'; // Default to 'all' if folder info not found
            }
        }
        
        // Build the URL in the format: /folder/{folder}/sort/{field}/{direction}/page/{page}
        let url = `/folder/${folderSlug}/sort/${sort}/${order}/page/${page}`;
        
        // Add per_page as a query parameter if not default
        if (perPage !== 25) {
            url += `?per_page=${perPage}`;
        }
        
        // Log the navigation for debugging
        console.log('Navigating to:', {
            folder,
            folderSlug,
            sort,
            order,
            page,
            perPage,
            url
        });
        
        window.location.href = url;
    }
}

// Initialize when the DOM is ready
document.addEventListener('DOMContentLoaded', () => {
    new CommandPalette();
}); 