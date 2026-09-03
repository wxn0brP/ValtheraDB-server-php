/** biome-ignore-all lint/suspicious/noExplicitAny: . */
import { RemoteActionsBase } from "@wxn0brp/db-client/actions";

const TEST_COLLECTIONS = ["users", "test", "items", "test_coll", "to_remove"];

declare const process: {
	env: {
		VALTHERA_PHP_URL?: string;
		VALTHERA_PHP_TOKEN?: string;
		VALTHERA_PHP_DB?: string;
	};
};

export default async () => {
	const url = process.env.VALTHERA_PHP_URL || "http://localhost:8085/php-db/";
	const token = process.env.VALTHERA_PHP_TOKEN || "test-token-789";
	const db = process.env.VALTHERA_PHP_DB || "vdb-test";

	const actions = new RemoteActionsBase({
		name: db,
		url: url,
		auth: token,
		headers: {
			Authorization: `Bearer ${token}`,
		},
	});

	actions._inited = false;

	actions._request = async (type: string, query?: any) => {
		const data = {
			auth: token,
			db: db,
			query: query,
			keys: [],
		};
		const targetUrl = `${url.replace(/\/$/, "")}/db/${type}.php`;
		const res = await fetch(targetUrl, {
			method: "POST",
			headers: {
				"Content-Type": "application/json",
				Authorization: `Bearer ${token}`,
			},
			body: JSON.stringify(data),
		});
		const json = await res.json();
		if (json.err) throw new Error(json.msg);
		return json.result;
	};

	actions.getCollections = async () => {
		return actions._request<string[]>("getCollections", {});
	};

	actions.init = async () => {
		for (const coll of TEST_COLLECTIONS) {
			try {
				await actions._request("removeCollection", coll);
			} catch {}
		}
		actions._inited = true;
	};

	return actions;
};
