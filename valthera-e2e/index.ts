import { RemoteActionsBase } from "@wxn0brp/db-client/actions";

const TEST_COLLECTIONS = [
	"users", "test", "items",
	"test_coll", "to_remove",
];

export default async () => {
	const url = process.env.VALTHERA_PHP_URL || "http://localhost:8085/php-db/";
	const token = process.env.VALTHERA_PHP_TOKEN || "test-token-789";
	const db = process.env.VALTHERA_PHP_DB || "vdb-test";

	const actions = new RemoteActionsBase({
		name: db,
		url: url,
		auth: token,
	});

	actions._inited = false;

	actions.getCollections = async () => {
		return actions._request<string[]>("getCollections", {});
	};

	actions.init = async () => {
		for (const coll of TEST_COLLECTIONS) {
			try { await actions._request("removeCollection", coll); }
			catch {}
		}
		actions._inited = true;
	};

	return actions;
};
