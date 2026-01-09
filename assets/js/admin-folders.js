(function () {
	if (!window.MFO_Admin) {
		return;
	}

	const apiRoot = window.MFO_Admin.root.replace(/\/$/, '');
	const nonce = window.MFO_Admin.nonce;
	const treeContainer = document.getElementById('mfo-folder-tree');
	const parentSelect = document.getElementById('mfo-folder-parent');
	const createButton = document.getElementById('mfo-create-folder');
	const nameInput = document.getElementById('mfo-folder-name');
	const message = document.getElementById('mfo-message');

	const request = (path, options) => {
		const settings = Object.assign(
			{
				credentials: 'same-origin',
				headers: {
					'X-WP-Nonce': nonce,
					'Content-Type': 'application/json',
				},
			},
			options || {}
		);

		return window.fetch(apiRoot + path, settings).then((response) => {
			if (!response.ok) {
				return response.json().then((data) => {
					throw data;
				});
			}
			return response.json();
		});
	};

	const setMessage = (text, isError) => {
		if (!message) {
			return;
		}
		message.textContent = text;
		message.className = isError ? 'mfo-message error' : 'mfo-message';
	};

	const buildTree = (nodes) => {
		const list = document.createElement('ul');
		list.className = 'mfo-folder-list';
		nodes.forEach((node) => {
			const item = document.createElement('li');
			item.className = 'mfo-folder-item';
			const label = document.createElement('span');
			label.textContent = `${node.name} (${node.count})`;
			label.className = 'mfo-folder-label';

			const actions = document.createElement('span');
			actions.className = 'mfo-folder-actions';

			const renameButton = document.createElement('button');
			renameButton.type = 'button';
			renameButton.className = 'button button-small';
			renameButton.textContent = window.MFO_Admin.i18n.rename;
			renameButton.addEventListener('click', () => {
				const newName = window.prompt(window.MFO_Admin.i18n.new_name, node.name);
				if (!newName) {
					return;
				}
				request(`/folders/${node.id}`, {
					method: 'PUT',
					body: JSON.stringify({ name: newName }),
				})
					.then(loadFolders)
					.catch((error) => {
						setMessage(error.message || 'Error', true);
					});
			});

			const deleteButton = document.createElement('button');
			deleteButton.type = 'button';
			deleteButton.className = 'button button-small';
			deleteButton.textContent = window.MFO_Admin.i18n.delete;
			deleteButton.addEventListener('click', () => {
				if (!window.confirm(window.MFO_Admin.i18n.delete_confirm)) {
					return;
				}
				request(`/folders/${node.id}`, {
					method: 'DELETE',
					body: JSON.stringify({ strategy: 'parent' }),
				})
					.then(loadFolders)
					.catch((error) => {
						setMessage(error.message || 'Error', true);
					});
			});

			actions.appendChild(renameButton);
			actions.appendChild(deleteButton);

			item.appendChild(label);
			item.appendChild(actions);

			if (node.children && node.children.length) {
				item.appendChild(buildTree(node.children));
			}

			list.appendChild(item);
		});
		return list;
	};

	const populateParentSelect = (nodes, depth = 0) => {
		nodes.forEach((node) => {
			const option = document.createElement('option');
			option.value = node.id;
			option.textContent = `${'— '.repeat(depth)}${node.name}`;
			parentSelect.appendChild(option);
			if (node.children && node.children.length) {
				populateParentSelect(node.children, depth + 1);
			}
		});
	};

	const loadFolders = () => {
		if (!treeContainer || !parentSelect) {
			return;
		}
		setMessage('', false);
		request('/folders')
			.then((data) => {
				parentSelect.innerHTML = '';
				const rootOption = document.createElement('option');
				rootOption.value = '0';
				rootOption.textContent = window.MFO_Admin.i18n.create;
				parentSelect.appendChild(rootOption);
				populateParentSelect(data.folders || []);

				treeContainer.innerHTML = '';
				if (!data.folders || !data.folders.length) {
					const empty = document.createElement('p');
					empty.textContent = window.MFO_Admin.i18n.no_folders;
					treeContainer.appendChild(empty);
					return;
				}
				treeContainer.appendChild(buildTree(data.folders));
			})
			.catch((error) => {
				setMessage(error.message || 'Error', true);
			});
	};

	if (createButton) {
		createButton.addEventListener('click', () => {
			const name = nameInput ? nameInput.value.trim() : '';
			const parent = parentSelect ? parseInt(parentSelect.value, 10) : 0;
			if (!name) {
				setMessage(window.MFO_Admin.i18n.name_required, true);
				return;
			}
			request('/folders', {
				method: 'POST',
				body: JSON.stringify({ name, parent }),
			})
				.then(() => {
					if (nameInput) {
						nameInput.value = '';
					}
					loadFolders();
					setMessage(window.MFO_Admin.i18n.created, false);
				})
				.catch((error) => {
					setMessage(error.message || 'Error', true);
				});
		});
	}

	loadFolders();
})();
